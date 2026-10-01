# Harvester IT Assistant — Stage 5 provider setup

The assistant works without an AI provider: curated troubleshooting answers are used first and unknown questions are escalated to a person.

To enable AI + web/retrieval fallback for unmatched questions, configure these as server-side environment variables in Hostinger/PHP:

- `IT_HELP_AI_ENDPOINT` — HTTPS endpoint that accepts the JSON payload documented below.
- `IT_HELP_AI_TOKEN` — optional bearer token for that endpoint.

Do not put API keys in JavaScript or HTML.

## Request contract
The site POSTs JSON containing `question`, a structured `diagnostic` object, and safety/source requirements.

## Response contract
Return JSON:

```json
{
  "answer": "Short diagnosis/explanation",
  "steps": ["Safe step 1", "Safe step 2"],
  "stop": "When the user should stop and escalate",
  "confidence": "low|medium|high",
  "sources": [
    {"title": "Official vendor article", "url": "https://..."}
  ]
}
```

Prefer official vendor documentation. The site accepts only HTTPS source URLs and caps the number/length of returned fields.
