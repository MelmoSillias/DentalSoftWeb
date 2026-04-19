Sakai is an application template for Vue based on the [create-vue](https://github.com/vuejs/create-vue), the recommended way to start a Vite-powered Vue projects.

Visit the [documentation](https://sakai.primevue.org/documentation) to get started.

## Multi-cabinet workflow

Frontend branding/assets are selected per cabinet from `cabinet-configs/<cabinet-id>/`.

### Folder structure

- `cabinet-configs/<cabinet-id>/config.json`: branding + PWA config
- `cabinet-configs/<cabinet-id>/public/**`: static files copied into `public/` before `dev/build`
- `src/generated/cabinet-config.generated.js`: generated file consumed by the app

### Commands

- `npm run dev`
	- Uses `default` cabinet automatically
- `npm run build`
	- Uses `default` cabinet automatically
- `npm run build:cabinet -- --cabinet=<cabinet-id>`
	- Builds for a specific cabinet

### Add a new cabinet

1. Create `cabinet-configs/<new-id>/config.json` based on `cabinet-configs/default/config.json`.
2. Add assets under `cabinet-configs/<new-id>/public/` (`favicon.ico`, `logo.png`, `icons/*`, etc.).
3. Run `npm run build:cabinet -- --cabinet=<new-id>` to validate.
