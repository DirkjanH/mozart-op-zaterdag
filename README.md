# mozart-op-zaterdag

## Databaseschema

De PHP-pagina's gaan uit van het reeds bijgewerkte databaseschema en voeren geen
automatische `ALTER TABLE`-opdrachten uit. De losse migratiebestanden in [SQL](SQL)
blijven bewaard; voer deze niet opnieuw uit op een database waarop ze al zijn toegepast.