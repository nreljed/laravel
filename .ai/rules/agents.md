---
paths:
  - 'src/Ai/Agents/**'
---

# Agents

## BYOK provider() returns config key + config()->set
Laravel AI SDK: agent provider() must return a config-key string ('openai'), NOT an inline ['driver','key'] array — the SDK treats an array as a failover list and Provider::providerCredentials() reads `key` from ai.providers.{driver} config. Inject the per-user BYOK key via config()->set("ai.providers.{driver}.key", $key) before prompting (ResolvesUserProvider::injectProviderKey). Safe per-request in PHP; the old "never config()->set()" guidance was wrong.
