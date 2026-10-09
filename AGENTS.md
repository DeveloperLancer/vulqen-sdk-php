# AGENTS.md — vulqen-sdk-php

Umieszczenie docelowe: `AGENTS.md` w repozytorium kodu. Linki `../vulqen-docs/` są liczone z tamtego katalogu.

Paczka `vulqen/php-sdk`. Kanon jest w repozytorium `vulqen-docs`, nie tutaj.

Lokalnie: [../vulqen-docs/README.md](../vulqen-docs/README.md).  
Na GitHubie: [vulqen-docs](https://github.com/DeveloperLancer/vulqen-docs).

Ten plik jest kopią [../vulqen-docs/repos/vulqen-sdk-php.AGENTS.md](../vulqen-docs/repos/vulqen-sdk-php.AGENTS.md). Przy zmianie zasad najpierw szablon.

## Zakres

Rdzeń bez Symfony: model envelope, normalizacja SQL, scrubbing, transport curl, obwód, próbkowanie. Opis: [../vulqen-docs/plan/30-sdk-php.md](../vulqen-docs/plan/30-sdk-php.md). Format na drucie: [../vulqen-docs/plan/20-protokol-ingestu.md](../vulqen-docs/plan/20-protokol-ingestu.md).

PHP 8.2+. Zależności runtime: rozszerzenia `curl`, `json`, `mbstring`. Bez frameworkowego klienta HTTP.

Paczka powstaje jako szkielet w Fazie 1. Wysyłka i model zdarzeń wchodzą w Fazie 4.

## Zasady

- Żadna metoda publiczna nie wypuszcza `\Throwable` do hosta.
- SDK nie instrumentuje własnego wywołania na ingest. To ograniczenie egzekwuje bundle; SDK nie dokleja śladu do transportu.
- Zmiana pól JSON zaczyna się w `vulqen-docs` i w `vulqen/spec`, a serwer je przyjmuje, zanim ta paczka zacznie je wysyłać.
- Fixtures bierz lokalnie z `../vulqen/spec`, gdy katalog istnieje. W CI z `tests/fixtures/spec` zgodnego z `VULQEN_SPEC_REF`. Przy podbiciu tagu odśwież kopię.
- `composer.json` nie zawiera repozytorium typu `path`.
- Katalog `.idea/` nie wchodzi do gita.

## Faza bieżąca

Patrz tabela w [../vulqen-docs/README.md](../vulqen-docs/README.md).
