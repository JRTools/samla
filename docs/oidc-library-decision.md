# Entscheidungsvorlage: OIDC-Client

Stand: 21. Oktober 2026. Status: isoliert erprobt, Aufnahme als Anwendungsabhängigkeit noch nicht freigegeben.

## Vorschlag

`facile-it/php-openid-client` in Version `1.0.0` als direkten OIDC-Client verwenden. Der ausgewertete Stand stammt aus dem offiziellen Composer-Paket und wurde ausschließlich außerhalb des Anwendungsprojekts installiert. Samlas composer.json, composer.lock und vendor wurden nicht verändert. Die Anwendung hat weiterhin keine aktive OIDC-Webanmeldung.

Der Client unterstützt den bestätigten Authorization-Code-Ablauf mit PKCE und Basic, verwendet eine separate JOSE-Prüfung und erlaubt die explizite Übergabe von HTTP-Client und Schlüssellieferant. Dadurch lassen sich die bereits vorhandenen Samla-Vertrauensgrenzen und reproduzierbare Negativtests anschließen. Statt einer selbst geschriebenen Signaturprüfung wird die gepflegte JOSE-Bibliothek verwendet. Dies ist eine Integrationsentscheidung, kein vollständiges Sicherheitsaudit des Fremdcodes.

Quellen: [offizielles Repository](https://github.com/facile-it/php-openid-client), [Composer-Paket](https://packagist.org/packages/facile-it/php-openid-client). Geprüfter Quellstand: `4257463518a8a9f1de6fe6014c130330030331fb`.

## Umfang der Abhängigkeiten

Die isolierte Auflösung ergab ein direktes Paket und 23 transitive Pakete. Guzzle oder ein separater Serverdienst sind nicht erforderlich. Der bereits transitive Symfony-HTTP-Client kann ausdrücklich als NativeHttpClient über den PSR-18-Adapter verwendet werden. Das verhindert automatische Transportwahl und benötigt für diesen Weg kein zusätzliches cURL- oder GMP-Modul. Die Composer-Plattformprüfung der konkreten Auflösung verlangt PHP sowie JSON, OpenSSL, iconv und mbstring; diese Fähigkeiten sind auf der Zielinstallation vor der Einführung zu prüfen.

| Paket | Geprüfte Version |
|---|---|
| facile-it/php-openid-client | 1.0.0 |
| facile-it/php-jose-verifier | 1.0.0 |
| web-token/jwt-library | 4.2.3 |
| brick/math | 1.0.0 |
| nyholm/psr7 | 1.8.2 |
| php-http/discovery | 1.20.0 |
| psr/clock | 1.0.0 |
| psr/container | 2.0.2 |
| psr/http-client | 1.0.3 |
| psr/http-factory | 1.1.0 |
| psr/http-message | 2.0 |
| psr/http-server-handler | 1.0.2 |
| psr/http-server-middleware | 1.0.2 |
| psr/log | 3.0.2 |
| psr/simple-cache | 3.0.0 |
| spomky-labs/aes-key-wrap | v7.0.0 |
| spomky-labs/base64url | v2.0.4 |
| spomky-labs/pki-framework | 1.6.3 |
| symfony/deprecation-contracts | v3.7.1 |
| symfony/http-client | v8.1.9 |
| symfony/http-client-contracts | v3.7.3 |
| symfony/polyfill-mbstring | v1.43.0 |
| symfony/polyfill-php83 | v1.43.0 |
| symfony/service-contracts | v3.7.3 |

Die Installation erfolgte mit deaktivierten Composer-Plugins und Scripts. Das Discovery-Paket ist selbst als Composer-Plugin markiert; dessen Plugin wird für unsere explizite Zusammenstellung nicht benötigt und bleibt gesperrt. Composer meldete bei der isolierten Auflösung keine bekannten Sicherheitsadvisories. Das ersetzt keine eigene Sicherheitsprüfung oder spätere Aktualisierung.

## Erprobung

Der reproduzierbare Prüfer `tests/Tools/oidc-library-probe.php` verwendet ausschließlich synthetische Daten und pro Lauf neu erzeugte RSA-Testschlüssel. Er läuft unabhängig von PHPUnit und einer Datenbank gegen eine ausdrücklich übergebene externe Evaluation-Autoload-Datei. Er wird nicht vom Webeinstieg geladen.

Unter PHP 8.4.5 und 8.5.4 sind erfolgreich:

- Gültiges RS256-ID-Token mit überprüfter Signatur und Rückgabe der geprüften Claims.
- Ablehnung von 16 Tokenfehlern: falsche Signatur, Issuer, Audience, Ablauf, zukünftiges iat/nbf, falscher Nonce, nicht freigegebenes Signaturverfahren, unsigned Token, unbekannte Schlüsselkennung, ungültiges Format und jeweils fehlendes iss/sub/aud/exp/iat.
- Tatsächlicher Bibliotheks-Tokenrequest an einen isolierten PSR-18-Testclient mit korrektem Basic-Header, Code, Callback und ursprünglichem PKCE-Verifier. Kein Secret im Requestbody.
- Nachladen bei einer vorher unbekannten Schlüsselkennung und anschließende erfolgreiche Tokenprüfung.
- Ablehnung eines OAuth-Tokenfehlers.
- Instanziierung der ausdrücklich gewählten NativeHttpClient-/PSR-18-Kombination ohne cURL und ohne Warnungen/Deprecations.
- Composer-Plattformprüfung der installierten Pakete gegen beide tatsächlichen PHP-Laufzeiten.

Es wurde kein echter Token-Endpunkt mit Testzugangsdaten aufgerufen. Schlüsselrotation ist im Test über einen kontrollierten Schlüssellieferanten geprüft, nicht über einen entfernten JWKS-Endpunkt. HTTP-Timeouts, Redirectverbote und Antwortgrößen sind bei der tatsächlichen Adapterintegration erneut für den Bibliothekstransport zu prüfen.

## Verbindliche Pflichten des Samla-Adapters

Die Bibliothek allein ist nicht die vollständige Samla-Anmelderichtlinie. Der Prüfer dokumentiert vier relevante Verhaltensweisen ausdrücklich:

1. Eine reine OAuth-Antwort ohne ID-Token ist für den Bibliotheksdienst zulässig. Samla muss sie für OIDC-Anmeldungen ablehnen.
2. Ein vorhandener falscher Nonce wird abgelehnt; ein vollständig fehlender Nonce ist durch die gezeigte Callback-Verwendung allein nicht verboten. Samla verlangt ihn nach erfolgreicher Signaturprüfung zwingend und vergleicht ihn mit der begonnenen Transaktion.
3. `azp` wird in der geprüften Standardzusammenstellung nicht auf unsere Client-ID eingeschränkt. Samla muss einen vorhandenen Wert prüfen und bei mehreren Audiences eine passende Authorized Party verlangen.
4. Die geprüfte Code-Callback-Methode bindet ein zurückgegebenes `at_hash` nicht automatisch an das Access-Token. Samla muss einen vorhandenen Hash ausdrücklich durch die Bibliotheksprüfung mit dem Access-Token prüfen lassen.

Außerdem bleibt die vorhandene State-/PKCE-/Callbackprüfung vor dem Austausch notwendig. Der verbrauchte Loginversuch muss unter Sessionlock gespeichert werden, bevor Netzwerk-I/O beginnt. Signaturverfahren werden ausdrücklich begrenzt, zunächst auf das im Test ausgewertete RS256; die reale Clientregistrierung muss dazu passen. Roh-Tokens, Basic-Header und Bibliotheksausnahmen dürfen nicht in HTTP-Fehlerseiten oder Logs gelangen. Verifizierte Claims sind vor Identitätsübernahme zusätzlich auf erwartete Typen und Grenzen zu prüfen.

## Reproduktion ohne Änderung der Anwendung

Eine eigene temporäre Evaluation anlegen. Die folgenden Composer-Befehle ausschließlich in diesem separaten Verzeichnis ausführen:

```sh
mkdir -p /tmp/samla-oidc-evaluation
composer --working-dir=/tmp/samla-oidc-evaluation init --name=samla/oidc-evaluation --require=facile-it/php-openid-client:1.0.0 --no-interaction
composer --working-dir=/tmp/samla-oidc-evaluation config allow-plugins false
composer --working-dir=/tmp/samla-oidc-evaluation install --no-plugins --no-scripts --no-interaction
```

Anschließend aus dem Samla-Repository unter beiden PHP-Versionen:

```sh
php tests/Tools/oidc-library-probe.php /tmp/samla-oidc-evaluation/vendor/autoload.php
composer --working-dir=/tmp/samla-oidc-evaluation check-platform-reqs --lock --no-dev
```

Spätere Composer-Auflösungen können andere transitive Versionen wählen. Für den tatsächlichen Einbau ist nach Freigabe ein Anwendungs-Lockfile zu erzeugen und dessen konkrete Auflösung nochmals zu prüfen.

## Noch erforderliche Entscheidung

AGENTS.md verlangt: „Keine zusätzlichen Laufzeitkomponenten ohne Abstimmung einführen.“ Die konkrete Aufnahme von `facile-it/php-openid-client 1.0.0` einschließlich der transitiven Abhängigkeiten ist deshalb vor der Änderung der Anwendungsabhängigkeiten abzustimmen. Die isolierte Erprobung ist abgeschlossen und reviewbar; diese Vorlage behauptet keine bereits erfolgte Freigabe.

Nach Zustimmung folgen Bibliotheksadapter, abgesicherter HTTP-Transport, vollständige Token-/Claimprüfung, Webrouten, Sitzungswechsel und Logout sowie Identitätsübernahme mit vorhandenen Sperr-/Rechteprüfungen. Gruppenclaim-Vertrag, Gruppenfrische und Erstadministration sind weiterhin getrennte offene Betriebseinstellungen. Der vollständige Login muss anschließend mit echtem Provider und unter den Zielplattformen geprüft werden.
