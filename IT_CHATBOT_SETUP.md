# G.I.T OpenAI connection on Hostinger

Saved troubleshooting stays available without an API key. AI is opt-in: only the current question is sent, never customer records or chat history.

Put `avesta-secrets.php` one directory ABOVE this site's `public_html`, returning a PHP array with `OPENAI_API_KEY`. Alternatively set that server environment variable. Never commit the key, put it in HTML/JavaScript or a public folder. PHP cURL must be enabled.

Deploy GitHub main, then open `/assistant-api.php?action=status`. `configured: true` means a plausible key was found and cURL is available; it does not validate the key or billing. The response never returns the key. Select “Use AI and online sources” in the chatbot and send a non-sensitive test question to verify the provider.

The connector uses OpenAI Responses with `gpt-4.1-mini`, one required `web_search` call and up to 800 output tokens. API usage and search are billable to the key's project. Configure project spending controls in OpenAI separately; this deployment does not alter them. Site-side caps: 10 attempts/hour/IP and 100 attempts/day total. Shared networks share the hourly cap. Rate state is PHP-guarded, IPs are hashed, no question text is stored, and failure to save counters blocks paid calls. Multi-server deployments require a shared limiter.

AI questions may be used by the search service; do not include secrets or personal information. `store: false` disables storing the response object, not every provider retention mechanism. Responses display source links, or disclose when none was cited. Troubleshooting remains advice only; the assistant cannot change devices, records or lending terms.

If OpenAI rejects the key, update the private file. For insufficient quota check API billing. For missing cURL enable the PHP cURL extension in Hostinger. Uncheck AI to use saved guidance after any error.
