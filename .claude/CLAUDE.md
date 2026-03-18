# Geldhelden AMI — Autonomous Marketing Intelligence

## Plugin-Info
- **WordPress Plugin**: `geldhelden-ami`
- **GitHub**: `mclac2000/geldhelden-ami`
- **Server-Pfad**: `~/www/geldhelden.org/wp-content/plugins/geldhelden-ami/`
- **Deploy**: `~/deploy-geldhelden-ami.sh`
- **Admin-URL**: `https://geldhelden.org/wp-admin/admin.php?page=geldhelden-ami`

## KI-Engine
- **Nur Claude** (claude-opus-4-6) — kein OpenAI, kein anderer Provider
- API Key in WP-Option `gami_claude_api_key` gespeichert
- Client: `includes/class-claude-client.php`

## Unterstützte Plattformen (13)
| Key | Plattform | Status |
|-----|-----------|--------|
| x | X/Twitter Ads | Implementiert |
| google | Google Ads (Search + Display + YouTube) | Implementiert |
| meta | Meta (Facebook + Instagram) | Implementiert |
| bing | Microsoft/Bing Ads | Implementiert |
| taboola | Taboola Native Ads | Implementiert |
| telegram_ads | Telegram Sponsored Messages | Implementiert |
| whatsapp | WhatsApp Business + Voice-Drop | Implementiert |
| pinterest | Pinterest Ads | Geplant Phase 2 |
| tiktok | TikTok Ads | Geplant Phase 2 |
| linkedin | LinkedIn Ads | Geplant Phase 2 |
| youtube | YouTube Ads (via Google) | Geplant Phase 2 |
| outbrain | Outbrain Native | Geplant Phase 2 |
| spotify | Spotify Audio Ads | Geplant Phase 3 |

## DB-Tabellen (alle mit Prefix `wp_ami_`)
- `products` — Produkt-Analysen (URL → USPs → Angles)
- `campaigns` — Kampagnen je Plattform
- `ads` — Ad-Varianten
- `ad_stats` — Tägliche Performance-Daten
- `landing_pages` + `lp_stats` — LP A/B-Tests
- `experiments` — A/B-Test-Protokoll
- `learnings` — Cross-Platform-Learnings
- `telegram_log` — Telegram-Kommunikation
- `decisions` — Agent-Entscheidungs-Log

## Telegram-Commands (@marsLA_bot)
```
!status       Kampagnen-Status aller Plattformen
!report       Vollständiger Bericht
!new [url]    Neue Kampagne starten
!pause [p]    Plattform pausieren
!resume [p]   Plattform fortsetzen
!learn        Cross-Platform-Learnings anzeigen
!ads [p]      Aktive Ads anzeigen
!stop         NOTFALL: Alle Kampagnen stoppen
!voice [url]  WhatsApp Voice-Skript generieren
!budget [p] [€] Budget setzen/anzeigen
```

## Cron-Loops
- **Alle 6h**: KPI-Fetch von allen Plattformen, Budget-Check
- **07:00 täglich**: Vollanalyse, Underperformer pausieren, A/B-Tests auswerten, Gewinner skalieren, Learnings extrahieren
- **So 06:00**: Wochenbericht an Marco via Telegram
- **Alle 2 Min**: Telegram-Polling für Befehle

## Entscheidungs-Schwellenwerte
- CTR < 0.3% bei 500+ Impressions → pausieren
- CPL > 15€ bei 50+ Conversions → überarbeiten
- ROAS < 1.5 bei 500€ Spend → Sofort-Alert
- A/B-Gewinner bei > 85% Konfidenz + min. 20 Conversions

## Cross-Platform Learning
Erkenntnisse aus einer Plattform werden automatisch auf andere übertragen:
- Medientyp (Video/Bild/Text) Lift-Analyse
- Angle-Performance (Fear/Benefit/Curiosity/Social Proof/Urgency)
- Timing (beste Wochentage)
- Produkt-Plattform-Fit (Webinar auf Cold Traffic, Kurs auf Retargeting etc.)
- Visuelle Muster (Farben, Layouts)

## Deploy-Workflow
```bash
# Lokal entwickeln
cd ~/development/geldhelden-ami
# ... Änderungen ...
git add -A && git commit -m "..."
git push origin main

# Server updaten
ssh hostpoint "~/deploy-geldhelden-ami.sh"
```
