-- Alertas por perfil de admin. O sistema cria isto sozinho; rode à mão só se o MySQL negar o ALTER.
ALTER TABLE alertas ADD COLUMN usuario_id INT NULL, ADD INDEX idx_alertas_usuario (usuario_id, lido);
