# VoltSpace AI Service (FastAPI)

Setup
- Install Python 3.10+.
- Create a virtual environment (optional) and install deps:
  - `pip install -r ai-service/requirements.txt`
- Provide your OpenAI API key:
  - Create `ai-service/.env` with: `OPENAI_API_KEY=sk-...` (auto-loaded)
  - Or set an env var (PowerShell): `$env:OPENAI_API_KEY = "sk-..."`
- Run the service:
  - `uvicorn ai-service.main:app --reload --port 8000`

Endpoints
- `POST /insights` — Input: list of devices. Returns `{ "insights": [...] }` with rule-based suggestions.
- `POST /agent` — Input: `{ "question": "..." }`. Uses OpenAI Chat Completions to answer.

Notes
- If `OPENAI_API_KEY` is not set (or missing in `.env`), `/agent` responds with a helpful message instead of failing.
- PHP app expects the service at `http://127.0.0.1:8000`.

