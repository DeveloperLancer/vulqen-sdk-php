# Changelog

Format z [05-repozytoria-i-wydania.md](https://github.com/DeveloperLancer/vulqen-docs/blob/main/plan/05-repozytoria-i-wydania.md): po co zmiana i czy upgrade wymaga kroku.

## 0.1.0

Pierwsze wydanie z wysyłką, żeby bundle Symfony mógł raportować żądania HTTP, wyjątki i SQL (Faza 4).

- `Client` jako jedyne wejście: transakcja, spany, wyjątki, `flush` i `reset`. Żadna metoda publiczna nie wypuszcza wyjątku.
- Envelope v1 zgodny z `spec-v1.1.0`.
- Transport curl z gzip, timeoutem połączenia 200 ms i całości 1000 ms, jednym ponowieniem tylko bez odpowiedzi HTTP.
- Obwód: trzy porażki otwierają go na 30 s, 429 na `Retry-After` do 60 s. Stan w APCu albo w pliku.
- Normalizacja SQL, scrubbing komunikatów i limit 20 wyjątków na minutę na odcisk.
- `max_spans` z bloku `config` odpowiedzi 202.

Kroki przy upgrade: brak. Pusty DSN wyłącza wysyłkę.
