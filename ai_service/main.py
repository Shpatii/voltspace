import os
from pathlib import Path
from datetime import datetime, timezone
from typing import Any, Dict, List, Optional
import time
import base64
import mimetypes

import requests
from fastapi import FastAPI, HTTPException, UploadFile, File, Form
from fastapi.staticfiles import StaticFiles
from fastapi.middleware.cors import CORSMiddleware
from pydantic import BaseModel, Field
from dotenv import load_dotenv
from openai import OpenAI

# ──────────────────────────────────────────────────────────────────────────────
# Load .env so API keys are read from ai-service/.env
# ──────────────────────────────────────────────────────────────────────────────
BASE_DIR = Path(__file__).resolve().parent
_dotenv = BASE_DIR / ".env"
if _dotenv.exists():
    load_dotenv(dotenv_path=_dotenv)

UPLOAD_DIR = BASE_DIR / "uploads"
STATIC_DIR = BASE_DIR / "static"
MODELS_DIR = STATIC_DIR / "models"
for p in (UPLOAD_DIR, MODELS_DIR):
    p.mkdir(parents=True, exist_ok=True)

app = FastAPI(title="VoltSpace AI Service")
app.mount("/static", StaticFiles(directory=str(STATIC_DIR)), name="static")

# Serve GLB with proper MIME and allow cross-origin from localhost
mimetypes.add_type('model/gltf-binary', '.glb')
app.add_middleware(
    CORSMiddleware,
    allow_origins=[
        "http://localhost",
        "http://localhost:80",
        "http://127.0.0.1",
        "http://127.0.0.1:80",
    ],
    allow_credentials=False,
    allow_methods=["GET", "POST"],
    allow_headers=["*"]
)

# ──────────────────────────────────────────────────────────────────────────────
# Models
# ──────────────────────────────────────────────────────────────────────────────
class Device(BaseModel):
    name: str
    type: str
    power_w: int = 0
    state: Dict[str, Any] = Field(default_factory=dict)
    last_active: Optional[datetime] = None


def hours_since(dt: Optional[datetime]) -> float:
    """Return hours since dt; robust to naive/aware datetimes."""
    if not dt:
        return 0.0
    if dt.tzinfo is None:
        dt = dt.replace(tzinfo=timezone.utc)
    now = datetime.now(timezone.utc)
    return max(0.0, (now - dt).total_seconds() / 3600.0)


# ──────────────────────────────────────────────────────────────────────────────
# Insights endpoint (unchanged logic; small robustness in hours_since above)
# ──────────────────────────────────────────────────────────────────────────────
@app.post("/insights")
def insights(devices: List[Device]):
    out: List[Dict[str, Any]] = []
    hour = datetime.now().hour
    for d in devices:
        t = (d.type or "").lower()
        on = bool(d.state.get("on", False))
        hrs = hours_since(d.last_active) if on else 0.0

        if t == "light" and on and hrs > 8:
            out.append({
                "severity": "warn",
                "title": f"Light on for {int(hrs)}h: {d.name}",
                "detail": f"The light '{d.name}' appears to be on for over {int(hrs)} hours. Consider turning it off.",
            })

        if t == "ac" and on and hrs > 6:
            out.append({
                "severity": "warn",
                "title": f"AC running {int(hrs)}h: {d.name}",
                "detail": f"'{d.name}' has been cooling for more than {int(hrs)} hours. Review setpoint or schedule.",
            })

        if t == "plug" and on and 0 <= hour <= 5 and d.power_w > 5:
            out.append({
                "severity": "info",
                "title": f"Night-time load on plug: {d.name}",
                "detail": f"'{d.name}' is using ~{d.power_w}W overnight (00:00–05:00). Consider turning it off.",
            })

        if t == "plug" and d.state.get("flexible"):
            out.append({
                "severity": "info",
                "title": f"Shiftable load: {d.name}",
                "detail": "This plug is marked flexible. Consider moving usage to 22:00–06:00 to save costs.",
            })

    return {"insights": out}


# AI-generated insights (uses OpenAI if key available)
@app.post("/insights_ai")
def insights_ai(devices: List[Device]):
    api_key = os.getenv("OPENAI_API_KEY")
    if not api_key:
        # Fallback to rule-based insights if no key configured
        return insights(devices)

    try:
        client = OpenAI(api_key=api_key)
        model = os.getenv("OPENAI_INSIGHTS_MODEL", os.getenv("OPENAI_MODEL", "gpt-4o-mini"))
        compact: List[Dict[str, Any]] = []
        for raw in devices:  # tolerate both Pydantic and raw dicts
            if isinstance(raw, dict):
                st = raw.get('state') or {}
                entry = {
                    'name': raw.get('name'),
                    'type': raw.get('type'),
                    'home': raw.get('home'),
                    'room': raw.get('room'),
                    'on': bool(raw.get('on', st.get('on', False))),
                    'power_w': int(raw.get('power_w') or 0),
                    'hours_on': float(raw.get('hours_on') or 0.0),
                    'last_active': raw.get('last_active'),
                    'hour_now': raw.get('hour_now'),
                    'attrs': {k: v for k, v in (st.items() if isinstance(st, dict) else []) if k != 'on'},
                }
            else:
                d = raw  # Device
                st = d.state or {}
                entry = {
                    'name': d.name,
                    'type': d.type,
                    'on': bool(st.get('on', False)),
                    'power_w': d.power_w,
                    'last_active': d.last_active.isoformat() if isinstance(d.last_active, datetime) else (str(d.last_active) if d.last_active else None),
                    'attrs': {k: v for k, v in st.items() if k != 'on'},
                }
            compact.append(entry)
        system = (
            "You are VoltSpace's energy analyst. Analyze the provided household devices and generate concise, actionable insights. "
            "Always return JSON with a top-level key 'insights' (1 to 5 items). Each item must have: severity in ['info','warn','critical'], title, detail. "
            "Use fields like on, hours_on, power_w, hour_now, room/home, and attrs (e.g., brightness, setpoint, flexible) to decide. "
            "Focus on: long-on lights (>8h), AC overuse (>6h), phantom loads at night (00:00-05:00), high draws, and shifting flexible plugs (22:00-06:00). "
            "If nothing critical, include at least one 'info' tip (e.g., cost shifting)."
        )
        import json
        user = "Devices JSON:\n" + json.dumps(compact, ensure_ascii=False)
        resp = client.chat.completions.create(
            model=model,
            messages=[
                {"role":"system","content":system},
                {"role":"user","content":user},
            ],
            temperature=0.2,
            max_tokens=600,
            timeout=30,
        )
        text = (resp.choices[0].message.content or "").strip()
        # Try reading as JSON; tolerate leading prose
        try:
            data = json.loads(text)
        except Exception:
            start = text.find('{')
            data = json.loads(text[start:]) if start >= 0 else {"insights": []}
        ins = data.get("insights", [])
        if not isinstance(ins, list) or len(ins) == 0:
            # Provide a soft default so caller saves something useful
            ins = [{
                "severity": "info",
                "title": "No critical issues detected",
                "detail": "Consider shifting flexible plug loads to 22:00–06:00 and turning off long-on lights."
            }]
        cleaned = []
        for i in ins:
            sev = (i.get("severity") or "info").lower()
            if sev not in ("info","warn","critical"): sev = "info"
            cleaned.append({
                "severity": sev,
                "title": i.get("title","Insight")[:128],
                "detail": i.get("detail",""),
            })
        return {"insights": cleaned}
    except Exception as e:
        # On error, fall back
        return insights(devices)


# ──────────────────────────────────────────────────────────────────────────────
# OpenAI agent (OpenAI SDK 1.x style)
# ──────────────────────────────────────────────────────────────────────────────
class AgentQuery(BaseModel):
    question: str
    context: Optional[dict | str] = None
    user_id: Optional[int] = None


@app.post("/agent")
def agent(q: AgentQuery):
    api_key = os.getenv("OPENAI_API_KEY")
    if not api_key:
        hint = " with context" if q.context else ""
        return {"answer": f"[Local demo] No OpenAI key set. Try using Dashboard & Insights; consider turning off long-running devices and shifting flexible loads{hint}."}

    model = os.getenv("OPENAI_MODEL", "gpt-4o-mini")

    try:
        client = OpenAI(api_key=api_key)
        messages = [
            {"role": "system", "content": "You are VoltSpace's home energy assistant. Be concise and actionable."}
        ]
        # Inject database context if provided by PHP
        if q.context is not None:
            try:
                import json
                ctx = q.context if isinstance(q.context, str) else json.dumps(q.context, ensure_ascii=False)
            except Exception:
                ctx = str(q.context)
            messages.append({"role": "system", "content": "Context from database (JSON/text):\n" + (ctx[:6000] if isinstance(ctx, str) else str(ctx))})
        messages.append({"role": "user", "content": q.question.strip()})

        resp = client.chat.completions.create(
            model=model,
            messages=messages,
            temperature=0.2,
            max_tokens=200,
            # OpenAI 1.x uses request timeouts via client config; this param is accepted by HTTPX under the hood.
            timeout=30,
        )
        answer = (resp.choices[0].message.content or "").strip()
        return {"answer": answer or "No answer."}

    # Fine-grained exceptions vary; keep user-friendly messages:
    except Exception as e:
        # Common helpful messages:
        msg = str(e)
        if "authentication" in msg.lower() or "auth" in msg.lower():
            return {"answer": "OpenAI authentication failed. Check OPENAI_API_KEY."}
        if "rate" in msg.lower() and "limit" in msg.lower():
            return {"answer": "OpenAI rate limit exceeded. Try again later."}
        if "timeout" in msg.lower() or "network" in msg.lower() or "connect" in msg.lower():
            return {"answer": "Network error reaching OpenAI. Check internet/proxy and try again."}
        return {"answer": f"Assistant error: {msg}"}


# ──────────────────────────────────────────────────────────────────────────────
# Meshy image → 3D (v1 JSON API + data URI; downloads GLB locally)
# ──────────────────────────────────────────────────────────────────────────────
@app.post("/meshify")
def meshify(image: UploadFile = File(...), hint: str = Form("smart home floor plan")):
    """Convert a 2D floor plan image to 3D via Meshy and serve a local GLB URL."""
    meshy_key = os.getenv("MESHY_API_KEY")
    if not meshy_key:
        raise HTTPException(status_code=500, detail="MESHY_API_KEY not set in ai-service/.env")

    if image.content_type not in ("image/png", "image/jpeg"):
        raise HTTPException(status_code=400, detail="Only PNG or JPG images are supported")

    # Save locally (optional)
    tmp_path = UPLOAD_DIR / f"{int(time.time()*1000)}_{image.filename}"
    with tmp_path.open("wb") as f:
        f.write(image.file.read())

    # Build data URI for Meshy JSON API
    mime = image.content_type or mimetypes.guess_type(tmp_path.name)[0] or "image/png"
    b64 = base64.b64encode(tmp_path.read_bytes()).decode("ascii")
    data_uri = f"data:{mime};base64,{b64}"

    # Submit task (JSON, not multipart)
    headers = {
        "Authorization": f"Bearer {meshy_key}",
        "Content-Type": "application/json",
    }
    payload = {
        "image_url": data_uri,      # v1 API expects a URL or data URI
        "should_texture": True,
        "should_remesh": True,
        "enable_pbr": True,
        # Optional: "subject": hint,
        # Optional: "background": "transparent",
    }

    resp = requests.post(
        "https://api.meshy.ai/openapi/v1/image-to-3d",
        headers=headers,
        json=payload,
        timeout=60,
    )
    if resp.status_code >= 300:
        raise HTTPException(status_code=resp.status_code, detail=f"Meshy submit failed: {resp.text}")

    task_id = (resp.json() or {}).get("result")
    if not task_id:
        raise HTTPException(status_code=500, detail="Meshy did not return a task id")

    # Poll task until complete (~7 minutes)
    deadline = time.time() + 420
    model_remote_url = None
    status = None
    last_payload = None

    while time.time() < deadline:
        pr = requests.get(
            f"https://api.meshy.ai/openapi/v1/image-to-3d/{task_id}",
            headers={"Authorization": f"Bearer {meshy_key}"},
            timeout=30,
        )
        if pr.status_code >= 300:
            raise HTTPException(status_code=pr.status_code, detail=f"Meshy task status failed: {pr.text}")

        tj = pr.json()
        last_payload = tj
        status = (tj.get("status") or "").upper()

        model_urls = tj.get("model_urls") or {}
        model_remote_url = model_urls.get("glb")

        if status in ("SUCCEEDED", "COMPLETED", "DONE") and model_remote_url:
            break
        if status in ("FAILED", "ERROR", "CANCELED"):
            raise HTTPException(status_code=500, detail=f"Meshy task failed: {tj}")

        time.sleep(3)

    if not (status and model_remote_url):
        raise HTTPException(
            status_code=504,
            detail=f"Meshy task timed out; last status: {status}, payload: {last_payload}",
        )

    # Download GLB locally so it’s served from FastAPI’s static dir
    dl = requests.get(model_remote_url, stream=True, timeout=180)
    if dl.status_code >= 300:
        raise HTTPException(status_code=dl.status_code, detail=f"Failed to download GLB: {dl.text}")

    glb_path = MODELS_DIR / f"{task_id}.glb"
    with glb_path.open("wb") as f:
        for chunk in dl.iter_content(chunk_size=8192):
            if chunk:
                f.write(chunk)

    return {"model_url": f"http://127.0.0.1:8000/static/models/{task_id}.glb"}

@app.post("/meshify/submit")
def meshify_submit(image: UploadFile = File(...), hint: str = Form("smart home floor plan")):
    import base64, mimetypes, requests, os, time
    from fastapi import HTTPException

    meshy_key = os.getenv("MESHY_API_KEY")
    if not meshy_key:
        raise HTTPException(500, "MESHY_API_KEY not set")

    if image.content_type not in ("image/png","image/jpeg"):
        raise HTTPException(400, "Only PNG/JPG supported")

    # save + data URI
    tmp_path = UPLOAD_DIR / f"{int(time.time()*1000)}_{image.filename}"
    tmp_path.write_bytes(image.file.read())
    
    mime = image.content_type or mimetypes.guess_type(tmp_path.name)[0] or "image/png"
    data_uri = f"data:{mime};base64,{base64.b64encode(tmp_path.read_bytes()).decode('ascii')}"

    headers = {"Authorization": f"Bearer {meshy_key}", "Content-Type": "application/json"}
    payload = {
        "image_url": data_uri,
        "should_texture": True,
        "should_remesh": True,
        "enable_pbr": True,
    }
    r = requests.post("https://api.meshy.ai/openapi/v1/image-to-3d", headers=headers, json=payload, timeout=60)
    if r.status_code >= 300:
        raise HTTPException(r.status_code, f"Meshy submit failed: {r.text}")
    task_id = (r.json() or {}).get("result")
    if not task_id:
        raise HTTPException(500, f"No task id. Raw: {r.text}")
    return {"task_id": task_id}

# -- Check status (raw passthrough)
@app.get("/meshify/status/{task_id}")
def meshify_status(task_id: str):
    import requests, os
    meshy_key = os.getenv("MESHY_API_KEY") or ""
    r = requests.get(f"https://api.meshy.ai/openapi/v1/image-to-3d/{task_id}",
                     headers={"Authorization": f"Bearer {meshy_key}"}, timeout=30)
    return {"code": r.status_code, "json": (r.json() if r.headers.get("content-type","").startswith("application/json") else r.text)}

# -- Download the GLB when ready
@app.post("/meshify/fetch_glb/{task_id}")
def meshify_fetch_glb(task_id: str):
    import requests, os, time
    r = requests.get(f"https://api.meshy.ai/openapi/v1/image-to-3d/{task_id}",
                     headers={"Authorization": f"Bearer {os.getenv('MESHY_API_KEY') or ''}"}, timeout=30)
    j = r.json()
    model_url = (j.get("model_urls") or {}).get("glb")
    if not model_url:
        raise HTTPException(404, f"No GLB in payload: {j}")
    dl = requests.get(model_url, stream=True, timeout=180)
    if dl.status_code >= 300:
        raise HTTPException(dl.status_code, f"GLB download failed: {dl.text}")
    glb_path = MODELS_DIR / f"{task_id}.glb"
    with glb_path.open("wb") as f:
        for c in dl.iter_content(8192):
            if c: f.write(c)
    return {"model_url": f"http://127.0.0.1:8000/static/models/{task_id}.glb"}
