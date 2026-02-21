# PWA Setup (frontend)

Ce projet a été préparé pour devenir une PWA via `vite-plugin-pwa`.

Étapes pour activer localement:

- Installer la dépendance de développement:

```powershell
cd frontend; npm install --save-dev vite-plugin-pwa
```

- Lancer en développement (le service worker fonctionne sur `localhost` sans HTTPS):

```powershell
cd frontend; npm run dev
```

- Pour tester la build finale et le service worker, build puis servir le dossier `dist` (préférer un serveur HTTPS pour tests réels):

```powershell
cd frontend; npm run build
npx serve -s dist
```

Notes importantes:

- Le manifest est auto-généré par le plugin et exposé à `/manifest.webmanifest`.
- J'ai ajouté des icônes placeholder dans `public/icons/` — remplacez-les avec vos images PNG finales.
- Les Service Workers ne s'exécutent que sur `https://` ou `http://localhost`.

Accès aux fichiers côté client:

- Pour accéder aux fichiers sur la machine cliente, utilisez l'API File System Access (Chrome/Edge/Opera). Exemple basique:

```javascript
// obtenir un fichier
const [fileHandle] = await window.showOpenFilePicker();
const file = await fileHandle.getFile();
const text = await file.text();
```

- Ajoutez une interface (bouton) pour déclencher `showOpenFilePicker()` depuis vos composants Vue.

Si vous voulez, je peux:
- Ajouter un petit composant d'exemple qui ouvre un fichier et l'affiche.
- Mettre en place des icônes PNG optimisées et une image splash pour mobile.
