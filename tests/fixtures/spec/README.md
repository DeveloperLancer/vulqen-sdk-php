# Specyfikacja envelope v1

JSON Schema w `envelope.v1.schema.json` (draft 2020-12) opisuje kontrakt z
`vulqen-docs/plan/20-protokol-ingestu.md`. Tag `spec-vX.Y.Z` na tym repozytorium
wskazuje wydanie kontraktu.

`fixtures/valid` przechodzi walidację. `fixtures/invalid` pada.

## Czego schema nie wyraża

Te reguły sprawdza serwer przy przyjęciu koperty, od Fazy 3:

- suma `buckets` równa się `count` w elemencie `stats`,
- `previous` w wyjątku ma głębokość co najwyżej 5; głębiej SDK obcina przed wysyłką,
- envelope zawierające metrykę o nazwie zaczynającej się od `host.`, `phpfpm.` albo
  `php.opcache.` wymaga `server_name`; brak powoduje pominięcie tych metryk, nie 400.

## Czego schema nie odrzuca świadomie

- Nieznany `type` elementu przechodzi. Serwer pomija taki element i liczy go w logu.
- Nieznane pole w znanym typie przechodzi. Serwer je ignoruje.
- Zły typ albo zła wartość znanego pola jest błędem całego envelope.
