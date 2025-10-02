curl https://openrouter.ai/api/v1/chat/completions \
  -H "Content-Type: application/json" \
  -H "Authorization: Bearer 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" \
  -d '{
  "model": "z-ai/glm-4.5-air:free",
  "messages": [
    {
      "role": "user",
      "content": "What is the meaning of life?"
    }
  ]
  
}'