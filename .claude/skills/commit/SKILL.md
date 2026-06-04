---
name: commit
allowed-tools: Bash(git add *), Bash(git status *), Bash(git diff *), Bash(git commit *)
description: Genera y ejecuta un commit con mensaje semántico basado en los cambios actuales
---

## Contexto

- Estado actual del repositorio: !`git status`
- Diff de cambios staged y unstaged: !`git diff HEAD`
- Último commit: !`git log --oneline -1`

## Tarea

Analiza los cambios mostrados arriba y genera un commit siguiendo estas reglas:

1. **Haz `git add`** de todos los archivos modificados relevantes (excluye `node_modules`, archivos de entorno como `.env`, y archivos de build)
2. **Genera el mensaje** usando formato de Conventional Commits:
   - `feat:` — nueva funcionalidad
   - `fix:` — corrección de bug
   - `chore:` — tareas de mantenimiento o configuración
   - `refactor:` — cambios que no añaden funcionalidad ni corrigen bugs
   - `docs:` — cambios en documentación
   - `style:` — formato, sin cambios de lógica
   - `test:` — añadir o corregir pruebas
3. El mensaje debe estar en **ingles** y ser descriptivo pero conciso (máx. 72 caracteres en el título)
4. Si los cambios son complejos, añade un cuerpo al commit explicando el "por qué"
5. Ejecuta el commit

## Ejemplo de mensaje esperado
feat: add user authentication module

Si hay archivos ambiguos o cambios en múltiples módulos distintos, pregunta antes de hacer el commit si deben ir juntos o separados.