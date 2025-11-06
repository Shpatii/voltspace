# VoltSpace — Local Demo

Smart home managing system with a PHP dashboard and a Python FastAPI AI service.

Features
- Create homes, rooms, and devices (light, AC, plug, sensor)
- Simulated device onboarding via serial (ENR-DEV-XXXXXX) or BT (BT-XXXX-XXXX)
- Dashboard: device counts, devices ON, estimated power, kWh today, longest-running list
- Run AI insights (rule-based) via FastAPI `/insights`
- Ask the assistant via OpenAI `/agent`

Directories
- `php-app/` — place contents under `XAMPP/htdocs/voltspace/`
- `ai-service/` — FastAPI app

Run PHP App (XAMPP)
- See `php-app/README_XAMPP_SETUP.md`

Run AI Service (FastAPI)
- See `ai-service/README_AI_SERVICE.md`

Demo Flow
1) Login with demo user (`demo@voltspace.local` / `Demo123!`) or register
2) Add a device via serial/BT wizard
3) Toggle devices; optionally update `last_active` in DB to simulate >8h ON
4) Run AI insights from dashboard
5) Ask the assistant a question

