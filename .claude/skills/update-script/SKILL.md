---
name: update-script
allowed-tools: Read, Edit, Bash(find *), Bash(grep *)
description: Revisa los módulos del proyecto y actualiza setup.sh con sus migraciones y seeders
---

## Contexto

Módulos presentes en la aplicación:
!`find app/Modules -maxdepth 1 -mindepth 1 -type d | sort`

Migraciones por módulo:
!`find app/Modules -path "*/Database/Migrations/*.php" | sort`

Seeders por módulo (nombre de clase y namespace):
!`grep -rh "^namespace\|^class " app/Modules --include="*Seeder*.php" | paste - - | sort`

Seeders del core (app/Database):
!`grep -rh "^namespace\|^class " app/Database --include="*Seeder*.php" | paste - - | sort`

Contenido actual de setup.sh:
!`cat setup.sh`

## Tarea

Analiza la información de contexto de arriba y actualiza `setup.sh` para que refleje exactamente los módulos y seeders que existen en este momento en el proyecto. Sigue estas reglas:

### 1. Array MODULES (migraciones)

Actualiza el bloque `declare -a MODULES=(...)` con un namespace por cada módulo que tenga archivos en `Database/Migrations/`. El orden debe ser:
1. `App\Modules\Core` siempre primero
2. El resto en orden alfabético

Formato de cada entrada:
```
"App\\Modules\\NombreModulo"
```

### 2. Bloque de seeders

Reconstruye las llamadas `run_seeder` del bloque `# ── Seeders ──` respetando este orden:

1. **CoreSeeder** (`App\Database\Seeds\CoreSeeder`) — siempre primero; crea roles, usuario admin y el módulo Communications
2. Por cada módulo adicional (en orden alfabético):
   a. Su `*ModuleSeeder` (registra el módulo en la tabla `modules` y otorga acceso a SuperAdmin)
   b. Cualquier otro seeder de datos del mismo módulo (coordinadores, catálogos, etc.)

Usa el nombre real de la clase PHP como segundo argumento de `run_seeder` (el label).

### 3. Restricciones

- No cambies ninguna otra sección del script (colores, funciones, lógica de Docker, verificaciones de entorno, etc.)
- Mantén el comentario de sección `# Core: roles, usuario admin y módulo Communications` antes del CoreSeeder
- Añade un comentario `# <NombreModulo>` antes del grupo de seeders de cada módulo adicional para facilitar la lectura
- Si un módulo tiene migraciones pero no seeders, igual inclúyelo en MODULES y simplemente no añadas `run_seeder` para él
- Si detectas un seeder nuevo que no estaba en setup.sh, agrégalo; si uno ya no existe en disco, elimínalo

### 4. Verificación final

Después de aplicar los cambios con Edit, muestra un resumen de qué se agregó, qué se eliminó y qué quedó igual.
