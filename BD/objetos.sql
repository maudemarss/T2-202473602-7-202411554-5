-- FUNCTION, VIEW, PROCEDURE y TRIGGERS
-- Ejecutar DESPUES de esquema.sql
USE salud_usm;

DROP VIEW IF EXISTS vw_citas_detalle;
DROP FUNCTION IF EXISTS fn_pct_inasistencia;
DROP PROCEDURE IF EXISTS sp_cancelar_citas_vencidas;
DROP TRIGGER IF EXISTS trg_max_3_especialidades;
DROP TRIGGER IF EXISTS trg_atencion_solo_atendida;
DROP TRIGGER IF EXISTS trg_cita_con_atencion_no_cambia;

-- ---------------------------------------------------------------------
-- VIEW: cita con todos sus datos unidos
-- Se usa en: Mis citas, Agenda, Busqueda avanzada y Comprobante
-- ---------------------------------------------------------------------
CREATE VIEW vw_citas_detalle AS
SELECT
    c.id_cita,
    c.fecha_hora,
    DATE(c.fecha_hora)            AS fecha,
    TIME(c.fecha_hora)            AS hora,
    p.id_paciente,
    p.rut                         AS rut_paciente,
    p.nombre_completo             AS nombre_paciente,
    p.telefono                    AS telefono_paciente,
    pr.id_prevision,
    pr.nombre                     AS prevision,
    m.id_medico,
    m.nombre_completo             AS nombre_medico,
    e.id_especialidad,
    e.nombre                      AS especialidad,
    cm.id_centro,
    cm.codigo_interno             AS codigo_centro,
    cm.nombre                     AS nombre_centro,
    cm.comuna,
    cm.region,
    ec.id_estado,
    ec.nombre                     AS estado
FROM Cita c
JOIN Paciente     p  ON p.id_paciente     = c.id_paciente
JOIN Prevision    pr ON pr.id_prevision   = p.id_prevision
JOIN Medico       m  ON m.id_medico       = c.id_medico
JOIN Especialidad e  ON e.id_especialidad = c.id_especialidad
JOIN CentroMedico cm ON cm.id_centro      = c.id_centro
JOIN EstadoCita   ec ON ec.id_estado      = c.id_estado;

DELIMITER $$

-- ---------------------------------------------------------------------
-- FUNCTION: porcentaje de inasistencia (estado 4 = No Asistio) de un centro
-- Se usa en Panel de gestion del administrador
-- ---------------------------------------------------------------------
CREATE FUNCTION fn_pct_inasistencia(p_id_centro INT)
RETURNS DECIMAL(5,2)
READS SQL DATA
BEGIN
    DECLARE v_total INT DEFAULT 0;
    DECLARE v_noasistio INT DEFAULT 0;

    SELECT COUNT(*), COALESCE(SUM(id_estado = 4), 0)
      INTO v_total, v_noasistio
      FROM Cita
     WHERE id_centro = p_id_centro;

    IF v_total = 0 THEN
        RETURN 0;
    END IF;
    RETURN ROUND(v_noasistio * 100 / v_total, 2);
END$$

-- ---------------------------------------------------------------------
-- PROCEDURE: pasa a Cancelada (5) las citas Reservada (1) o Confirmada (2)
-- devuelve cuantas citas se cancelaron.
-- ---------------------------------------------------------------------
CREATE PROCEDURE sp_cancelar_citas_vencidas(OUT p_canceladas INT)
BEGIN
    UPDATE Cita
       SET id_estado = 5
     WHERE id_estado IN (1, 2)
       AND fecha_hora < NOW();
    SET p_canceladas = ROW_COUNT();
END$$

-- ---------------------------------------------------------------------
-- TRIGGER 1: un medico no puede tener mas de 3 especialidades
-- ---------------------------------------------------------------------
CREATE TRIGGER trg_max_3_especialidades
BEFORE INSERT ON Medico_Especialidad
FOR EACH ROW
BEGIN
    IF (SELECT COUNT(*) FROM Medico_Especialidad
         WHERE id_medico = NEW.id_medico) >= 3 THEN
        SIGNAL SQLSTATE '45000'
        SET MESSAGE_TEXT = 'Un médico no puede tener más de 3 especialidades';
    END IF;
END$$

-- ---------------------------------------------------------------------
-- TRIGGER 2: solo puede existir una atencion si la cita esta Atendida
-- ---------------------------------------------------------------------
CREATE TRIGGER trg_atencion_solo_atendida
BEFORE INSERT ON Atencion
FOR EACH ROW
BEGIN
    IF (SELECT id_estado FROM Cita WHERE id_cita = NEW.id_cita) <> 3 THEN
        SIGNAL SQLSTATE '45000'
        SET MESSAGE_TEXT = 'Solo se puede registrar una atención si la cita está en estado Atendida';
    END IF;
END$$

-- ---------------------------------------------------------------------
-- TRIGGER 3: Si la cita ya tiene atencion, no se le puede cambiar el estado a algo distinto de Atendida.
-- ---------------------------------------------------------------------
CREATE TRIGGER trg_cita_con_atencion_no_cambia
BEFORE UPDATE ON Cita
FOR EACH ROW
BEGIN
    IF NEW.id_estado <> 3
       AND EXISTS (SELECT 1 FROM Atencion WHERE id_cita = OLD.id_cita) THEN
        SIGNAL SQLSTATE '45000'
        SET MESSAGE_TEXT = 'La cita ya tiene una atención registrada, no puede cambiar de estado';
    END IF;
END$$

DELIMITER ;
