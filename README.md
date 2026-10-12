# Tarea 2 Bases de Datos SaludUSM

Repositorio: https://github.com/maudemarss/T2_202473602-7_202411554-5

## Integrantes
- Agustin Zaldivar -  202473602-7
- Mauricio Gonzalez -  202411554-5

## Estructura
- `PDF/Normalizacion_3FN.pdf`: pasos de normalización (1FN, 2FN, 3FN).
- `PHP/`: aplicación web.
- `BD/saludusm.sql`: es la unión de `esquema.sql`, `objetos.sql` y `datos.sql`

## Como ejecutar
1. Instalar XAMPP con Apache, PHP y MySQL/MariaDB, y encender ambos servicios.
2. En phpMyAdmin, importar `BD/saludusm.sql` crea la base `salud_usm`
3. Copiar la carpeta `PHP` a `htdocs` (por ejemplo `htdocs/saludusm`).
4. La conexion esta en `PHP/conexion.php` host `localhost`, usuario `root`, sin contraseña
5. Abrir `http://localhost/saludusm/login.php` Se necesita internet
## Usuarios de prueba
| Rol | Correo | Contraseña |
|---|---|---|
| Administrador | admin@saludusm.cl | Admin123 |
| Médico (2 especialidades y 2 centros: Medicina General y Cardiología, en Valparaíso y Viña del Mar; tiene 4 citas el día de hoy) | medico01@saludusm.cl | Medico123 |
| Médico (de medico01 a medico15, misma contraseña) | medico02@saludusm.cl | Medico123 |
| Paciente (con citas próximas e históricas) | paciente01@correo.cl | Paciente123 |
| Paciente (de paciente01 a paciente40, misma contraseña) | paciente10@correo.cl | Paciente123 |

## Supuestos
- **Contraseña:** mínimo 8 caracteres, con al menos una letra y un numero, se guarda con `password_hash`.
- **RUT:** formato `XXXXXXXX-X` 7 u 8 dígitos, guion y dígito verificador o K) no se valida el dígito verificador
- **Horario y bloques:** atención de 09:00 a 18:00, en bloques de 30 minutos (último bloque 17:30). Solo se muestran los bloques libres del medico, que no estén ocupados por otra cita del mismo paciente y que no hayan pasado
- **Citas vencidas:** las citas Reservada o Confirmada con fecha anterior a la actual pasan a Cancelada
- **Eliminar cuenta (paciente):** se borra el usuario y, en cascada, el paciente con todas sus citas y atenciones
- **Eliminar médico (administrador):** se borra su usuario y asignaciones, pero solo si no tiene citas; si tiene citas el sistema lo impide
- **Quitar especialidad o centro a un médico:** si el médico tiene citas asociadas a esa especialidad o centro, el sistema lo impide
- **Eliminar centro:** el sistema lo impide si tiene citas, o si es el único centro de algun médico.
- **Estado de las citas:** el paciente solo reprograma o cancela citas Reservada o Confirmada con fecha futura, el médico solo puede cambiar sus citas a Confirmada, Atendida o No Asistió
- **Atención:** solo se puede registrar si la cita está Atendida y pertenece al médico (lo valida la aplicación y el trigger `trg_atencion_solo_atendida`). Debe tener al menos un diagnóstico; la receta puede quedar vacía. El paciente solo puede ver su historial.
- **Administrador:** es un usuario con rol 3 y no se puede registrar desde el login, solo existe el de prueba