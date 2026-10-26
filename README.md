# mozart-op-zaterdag

## Databaseschema

De PHP-pagina's gaan uit van het reeds bijgewerkte databaseschema en voeren geen
automatische `ALTER TABLE`-opdrachten uit. De losse migratiebestanden in [SQL](SQL)
blijven bewaard; voer deze niet opnieuw uit op een database waarop ze al zijn toegepast.

## Plaatsingen per halfjaar mailen

Op **Onderhoud > Deelnemers mailen** staan knoppen voor de eerste en tweede helft
van volgend jaar. Het jaar wordt automatisch bepaald. Elke knop selecteert alle
deelnemers met minstens één beschikbaarheid **ja** of **misschien** in die helft,
ook wanneer zij nergens zijn geplaatst. Iedere deelnemer ontvangt één mail.

De invoegcode `{{plaatsing}}` toont een tabel met datum, activiteit, locatie en
plaatsingsbesluit voor die deelnemer. **Geplaatst** wordt groen weergegeven,
inclusief instrument en partij; **Afgewezen** wordt rood weergegeven.
Activiteiten met beschikbaarheid **nee** en activiteiten buiten de gekozen
helft worden weggelaten. Controleer vooraf alle toelatingsbesluiten: het systeem
kent geen aparte status voor nog niet beoordeelde aanmeldingen, dus niet
toegelaten betekent hier afgewezen.

De standaardtekst voor een halfjaarmail bevat `{{plaatsing}}`. Handmatige
ontvangerselectie, serverconcepten, de testmail naar Dirkjan en verzending in
plukjes blijven beschikbaar. De plaatsingen worden bij het starten van de
wachtrij vastgelegd. `{{datum}}`, `{{plaats}}` en `{{omschrijving}}` verwijzen bij
deze doelgroepen naar de eerste activiteit van de gekozen helft;
`{{plaatsing}}` geeft het volledige persoonlijke overzicht.