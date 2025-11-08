# VoltSpace AI Service (FastAPI)

Setup
- Install Python 3.10+.
- Create a virtual environment (optional) and install deps:
  - `pip install -r ai-service/requirements.txt`
- Provide your OpenAI API key:
  - Create `ai-service/.env` with: `OPENAI_API_KEY=sk-...` (auto-loaded)
  - Or set an env var (PowerShell): `$env:OPENAI_API_KEY = "sk-..."`
- Optional: choose a model (default `gpt-3.5-turbo`):
  - In `.env`: `OPENAI_MODEL=gpt-3.5-turbo`
- Run the service:
  - `uvicorn ai_service.main:app --reload --port 8000`

Endpoints
- `POST /insights` — Input: list of devices. Returns `{ "insights": [...] }` with rule-based suggestions.
- `POST /agent` — Input: `{ "question": "..." }`. Uses OpenAI Chat Completions to answer.

Notes
- If `OPENAI_API_KEY` is not set (or missing in `.env`), `/agent` responds with a helpful message instead of failing.
- On auth/rate-limit/network errors, `/agent` returns HTTP 200 with a descriptive message in `answer` (no 500).
- PHP app expects the service at `http://127.0.0.1:8000`.

