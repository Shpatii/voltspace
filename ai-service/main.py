import os
from pathlib import Path
from datetime import datetime, timezone
from typing import Any, Dict, List, Optional

from fastapi import FastAPI, HTTPException
from pydantic import BaseModel, Field
from dotenv import load_dotenv

# Load .env so OPENAI_API_KEY is picked up automatically from ai-service/.env
_dotenv = Path(__file__).resolve().parent / ".env"
if _dotenv.exists():
    load_dotenv(dotenv_path=_dotenv)

app = FastAPI(title="VoltSpace AI Service")


class Device(BaseModel):
    name: str
    type: str
    power_w: int = 0
    state: Dict[str, Any] = Field(default_factory=dict)
    last_active: Optional[datetime] = None


def hours_since(dt: Optional[datetime]) -> float:
    if not dt:
        return 0.0
    now = datetime.now(timezone.utc).astimezone(dt.tzinfo)
    return max(0.0, (now - dt).total_seconds() / 3600.0)


@app.post("/insights")
def insights(devices: List[Device]):
    out: List[Dict[str, Any]] = []
    now = datetime.now()
    hour = now.hour
    for d in devices:
        t = (d.type or '').lower()
        on = bool(d.state.get('on', False))
        hrs = hours_since(d.last_active) if on else 0.0

        if t == 'light' and on and hrs > 8:
            out.append({
                'severity': 'warn',
                'title': f"Light on for {int(hrs)}h: {d.name}",
                'detail': f"The light '{d.name}' appears to be on for over {int(hrs)} hours. Consider turning it off."
            })

        if t == 'ac' and on and hrs > 6:
            out.append({
                'severity': 'warn',
                'title': f"AC running {int(hrs)}h: {d.name}",
                'detail': f"'{d.name}' has been cooling for more than {int(hrs)} hours. Review setpoint or schedule."
            })

        if t == 'plug' and on and 0 <= hour <= 5 and d.power_w > 5:
            out.append({
                'severity': 'info',
                'title': f"Night-time load on plug: {d.name}",
                'detail': f"'{d.name}' is using ~{d.power_w}W overnight (00:00–05:00). Consider turning it off."
            })

        if t == 'plug' and d.state.get('flexible'):
            out.append({
                'severity': 'info',
                'title': f"Shiftable load: {d.name}",
                'detail': "This plug is marked flexible. Consider moving usage to 22:00–06:00 to save costs."
            })

    return {"insights": out}


class AgentQuery(BaseModel):
    question: str


@app.post("/agent")
def agent(q: AgentQuery):
    api_key = os.getenv('OPENAI_API_KEY')
    if not api_key:
        # Provide a helpful offline fallback
        return {"answer": "OpenAI API key not set. Add OPENAI_API_KEY to ai-service/.env and restart the service."}

    try:
        import openai  # type: ignore
        openai.api_key = api_key
        completion = openai.ChatCompletion.create(
            model="gpt-3.5-turbo",
            messages=[
                {"role": "system", "content": "You are VoltSpace's home energy assistant. Be concise and actionable."},
                {"role": "user", "content": q.question.strip()},
            ],
            temperature=0.2,
            max_tokens=200,
        )
        answer = completion["choices"][0]["message"]["content"].strip()
        return {"answer": answer}
    except Exception as e:
        raise HTTPException(status_code=500, detail=str(e))

