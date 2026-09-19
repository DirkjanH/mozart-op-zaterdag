ALTER TABLE deelnemers
    ADD COLUMN nieuw_of_gewijzigd TINYINT(1) NOT NULL DEFAULT 0 AFTER op_de_hoogte_houden;