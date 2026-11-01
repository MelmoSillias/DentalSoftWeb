# Archives

Ce dossier rassemble les éléments qui ne participent pas au déploiement actuel.

- `local/` (ignoré par Git) : dumps et scripts de reprise par cabinet (`cdos_prod`,
  `orodent-prod`, `orodent_prod`, `mondentiste-prod`), copie `prod-local-api`,
  ancien exécutable Mercure Windows et fichiers RAR.
- `notes/` : notes de travail et demandes fonctionnelles historiques.

Les archives locales ne sont jamais envoyées au contexte de build Docker.
L'utilitaire encore utilisé par les imports de données a été conservé dans
[les scripts de l'API](../backend-reform/scripts/fix_dump.py).

Les lanceurs manuels à la racine et [Mercure autonome](../mercure-prod/) sont
conservés à leurs emplacements habituels pour les déploiements individuels.
