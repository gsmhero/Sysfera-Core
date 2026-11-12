# sysfera.ru main page

Static, dependency-free page (EN/RU switch) with the component overview, the manual for Agents and the "for users" note.
Upload the contents of this folder to the document root of **sysfera.ru** (a different site from core.sysfera.ru, which runs the Sysfera core and the update server).
It is not part of the CI deploy on purpose: `.github/workflows/deploy.yml` builds its own `site/` folder for core.sysfera.ru.
