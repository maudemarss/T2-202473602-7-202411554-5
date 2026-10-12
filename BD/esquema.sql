-- =====================================================================
-- tablas + catalogo
-- =====================================================================
DROP DATABASE IF EXISTS salud_usm;
CREATE DATABASE salud_usm CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE salud_usm;

-- ---------------------------------------------------------------------
-- CATALOGOS
-- ---------------------------------------------------------------------
CREATE TABLE Rol (
    id_rol TINYINT PRIMARY KEY,
    nombre VARCHAR(30) NOT NULL UNIQUE
);

CREATE TABLE Prevision (
    id_prevision TINYINT PRIMARY KEY,
    nombre VARCHAR(50) NOT NULL UNIQUE
);

CREATE TABLE Especialidad (
    id_especialidad INT AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(100) NOT NULL UNIQUE
);

CREATE TABLE EstadoCita (
    id_estado TINYINT PRIMARY KEY,
    nombre VARCHAR(50) NOT NULL UNIQUE
);

INSERT INTO Rol (id_rol, nombre) VALUES
(1, 'Paciente'), (2, 'Médico'), (3, 'Administrador');

INSERT INTO Prevision (id_prevision, nombre) VALUES
(1, 'Fonasa'), (2, 'Isapre'), (3, 'Particular');

INSERT INTO EstadoCita (id_estado, nombre) VALUES
(1, 'Reservada'), (2, 'Confirmada'), (3, 'Atendida'),
(4, 'No Asistió'), (5, 'Cancelada');

INSERT INTO Especialidad (nombre) VALUES
('Medicina General'), ('Cardiología'), ('Pediatría'), ('Traumatología'),
('Dermatología'), ('Neurología'), ('Ginecología'), ('Psiquiatría');

-- ---------------------------------------------------------------------
-- USUARIOS
-- ---------------------------------------------------------------------
CREATE TABLE Usuario (
    id_usuario INT AUTO_INCREMENT PRIMARY KEY,
    email VARCHAR(150) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    id_rol TINYINT NOT NULL,
    FOREIGN KEY (id_rol) REFERENCES Rol(id_rol)
);

-- ---------------------------------------------------------------------
-- ENTIDADES PRINCIPALES
-- ---------------------------------------------------------------------
CREATE TABLE CentroMedico (
    id_centro INT AUTO_INCREMENT PRIMARY KEY,
    codigo_interno VARCHAR(20) NOT NULL UNIQUE,
    nombre VARCHAR(100) NOT NULL,
    comuna VARCHAR(100) NOT NULL,
    region VARCHAR(100) NOT NULL
);

CREATE TABLE Paciente (
    id_paciente INT AUTO_INCREMENT PRIMARY KEY,
    id_usuario INT NOT NULL UNIQUE,
    rut VARCHAR(10) NOT NULL UNIQUE,
    nombre_completo VARCHAR(150) NOT NULL,
    fecha_nacimiento DATE NOT NULL,
    sexo VARCHAR(10) NOT NULL,
    id_prevision TINYINT NOT NULL,
    telefono VARCHAR(20) NULL,
    comuna_residencia VARCHAR(100) NOT NULL,
    CONSTRAINT chk_pac_rut CHECK (rut REGEXP '^[0-9]{7,8}-[0-9Kk]$'),
    CONSTRAINT chk_pac_sexo CHECK (sexo IN ('M', 'F', 'Otro')),
    -- cuando se borra el usuario se borra el paciente y sus citas
    FOREIGN KEY (id_usuario) REFERENCES Usuario(id_usuario) ON DELETE CASCADE,
    FOREIGN KEY (id_prevision) REFERENCES Prevision(id_prevision)
);

CREATE TABLE Medico (
    id_medico INT AUTO_INCREMENT PRIMARY KEY,
    id_usuario INT NOT NULL UNIQUE,
    rut VARCHAR(10) NOT NULL UNIQUE,
    nombre_completo VARCHAR(150) NOT NULL,
    CONSTRAINT chk_med_rut CHECK (rut REGEXP '^[0-9]{7,8}-[0-9Kk]$'),
    FOREIGN KEY (id_usuario) REFERENCES Usuario(id_usuario) ON DELETE CASCADE
);

-- ---------------------------------------------------------------------
-- RELACIONES MUCHOS A MUCHOS
-- ---------------------------------------------------------------------
CREATE TABLE Medico_Especialidad (
    id_medico INT NOT NULL,
    id_especialidad INT NOT NULL,
    PRIMARY KEY (id_medico, id_especialidad),
    FOREIGN KEY (id_medico) REFERENCES Medico(id_medico) ON DELETE CASCADE,
    FOREIGN KEY (id_especialidad) REFERENCES Especialidad(id_especialidad) ON DELETE CASCADE
);

CREATE TABLE Medico_Centro (
    id_medico INT NOT NULL,
    id_centro INT NOT NULL,
    PRIMARY KEY (id_medico, id_centro),
    FOREIGN KEY (id_medico) REFERENCES Medico(id_medico) ON DELETE CASCADE,
    FOREIGN KEY (id_centro) REFERENCES CentroMedico(id_centro) ON DELETE CASCADE
);

-- ---------------------------------------------------------------------
-- CITAS
-- Las dos FK COMPUESTAS obligan a que el medico tenga esa especialidad
-- y atienda en ese centro. Con RESTRICT, no se puede quitar una
-- especialidad/centro a un medico que tenga citas con ellos.
-- ---------------------------------------------------------------------
CREATE TABLE Cita (
    id_cita INT AUTO_INCREMENT PRIMARY KEY,
    fecha_hora DATETIME NOT NULL,
    id_paciente INT NOT NULL,
    id_medico INT NOT NULL,
    id_centro INT NOT NULL,
    id_especialidad INT NOT NULL,
    id_estado TINYINT NOT NULL DEFAULT 1,
    activa TINYINT AS (IF(id_estado = 5, NULL, 1)) STORED,
    -- sin sobreagendamiento
    CONSTRAINT unq_medico_hora UNIQUE (id_medico, fecha_hora, activa),
    CONSTRAINT unq_paciente_hora UNIQUE (id_paciente, fecha_hora, activa),
    FOREIGN KEY (id_paciente) REFERENCES Paciente(id_paciente) ON DELETE CASCADE,
    FOREIGN KEY (id_estado) REFERENCES EstadoCita(id_estado),
    FOREIGN KEY (id_medico, id_especialidad)
        REFERENCES Medico_Especialidad(id_medico, id_especialidad)
        ON DELETE RESTRICT ON UPDATE CASCADE,
    FOREIGN KEY (id_medico, id_centro)
        REFERENCES Medico_Centro(id_medico, id_centro)
        ON DELETE RESTRICT ON UPDATE CASCADE
);

-- ---------------------------------------------------------------------
-- ATENCIONES DIAGNOSTICOS Y RECETAS
-- ---------------------------------------------------------------------
CREATE TABLE Atencion (
    id_atencion INT AUTO_INCREMENT PRIMARY KEY,
    id_cita INT NOT NULL UNIQUE,
    motivo_consulta TEXT NOT NULL,
    observaciones TEXT NOT NULL,
    FOREIGN KEY (id_cita) REFERENCES Cita(id_cita) ON DELETE CASCADE
);

CREATE TABLE Diagnostico (
    codigo_cie10 VARCHAR(10) PRIMARY KEY,
    descripcion VARCHAR(255) NOT NULL
);

CREATE TABLE Atencion_Diagnostico (
    id_atencion INT NOT NULL,
    codigo_cie10 VARCHAR(10) NOT NULL,
    PRIMARY KEY (id_atencion, codigo_cie10),
    FOREIGN KEY (id_atencion) REFERENCES Atencion(id_atencion) ON DELETE CASCADE,
    FOREIGN KEY (codigo_cie10) REFERENCES Diagnostico(codigo_cie10) ON DELETE CASCADE
);

CREATE TABLE Receta (
    id_receta INT AUTO_INCREMENT PRIMARY KEY,
    id_atencion INT NOT NULL,
    medicamento VARCHAR(150) NOT NULL,
    dosis VARCHAR(255) NOT NULL,
    dias_tratamiento INT NOT NULL,
    CONSTRAINT chk_dias CHECK (dias_tratamiento > 0),
    FOREIGN KEY (id_atencion) REFERENCES Atencion(id_atencion) ON DELETE CASCADE
);