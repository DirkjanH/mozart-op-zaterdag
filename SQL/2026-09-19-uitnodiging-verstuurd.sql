ALTER TABLE activiteit_deelnemers
    ADD COLUMN uitnodiging_verstuurd_op DATETIME NULL AFTER status,
    ADD INDEX idx_activiteit_deelnemers_uitnodiging (activiteit_id, uitnodiging_verstuurd_op);