"""One-shot, copy-only architectural migration. Refuses to run twice."""
from pathlib import Path
import json
import re
import posixpath

ROOT = Path(__file__).resolve().parents[1]
SRC = ROOT / 'src'
MANIFEST = ROOT / 'architecture-map.json'
if MANIFEST.exists():
    raise SystemExit('Architecture already migrated; refusing to overwrite it.')

prefixes = {
    'router/': 'app/router/', 'layout/': 'app/layout/',
    'views/patients/': 'domains/patient/views/',
    'views/consultations/': 'domains/care-delivery/views/',
    'views/agenda/': 'domains/scheduling/views/',
    'views/caisse/': 'domains/billing/views/',
    'views/focus/': 'domains/focus/views/',
    'views/manual/': 'domains/docs/views/',
    'views/rapport/': 'domains/reporting/views/',
    'views/settings/': 'domains/settings/views/',
    'views/vitrine/': 'domains/communication/views/vitrine/',
    'views/pages/auth/': 'domains/auth/views/',
    'views/pages/': 'app/views/pages/', 'views/uikit/': 'app/views/uikit/',
    'components/patients/': 'domains/patient/components/',
    'components/consultations/': 'domains/care-delivery/components/',
    'components/fiche-medicale/': 'domains/clinical-record/components/',
    'components/agenda/': 'domains/scheduling/components/',
    'components/salles/': 'domains/scheduling/components/salles/',
    'components/caisse/': 'domains/billing/components/',
    'components/finances/': 'domains/billing/components/finances/',
    'components/administration/finances/': 'domains/billing/components/administration/',
    'components/administration/': 'domains/workforce/components/',
    'components/consumables/': 'domains/inventory/components/',
    'components/focus/': 'domains/focus/components/',
    'components/rapport/': 'domains/reporting/components/',
    'components/profile/': 'domains/auth/components/',
    'components/notifications/': 'domains/communication/components/notifications/',
    'components/vitrine/': 'domains/communication/components/vitrine/',
    'components/print/': 'shared/print/components/',
    'components/dashboard/': 'app/components/dashboard/',
    'components/landing/': 'app/components/landing/',
    'components/layout/': 'app/components/layout/',
    'components/': 'shared/ui/',
    'service/': 'shared/api/',
    'services/': 'domains/settings/services/',
    'stores/': 'domains/settings/stores/',
    'composables/realtime/': 'shared/realtime/',
    'composables/': 'shared/composables/',
    'utils/': 'shared/lib/',
    'locales/': 'app/locales/',
    'tours/': 'app/tours/',
    'styles/': 'app/styles/',
}
exact = {'main.js': 'app/main.js', 'App.vue': 'app/App.vue',
         'views/Profile.vue': 'domains/auth/views/Profile.vue'}

def assign(folder, names, target):
    for name in names.split():
        exact[f'{folder}/{name}'] = f'{target}/{name}'

assign('views/administration', 'Consommables.vue', 'domains/inventory/views')
assign('views/administration', 'EmployeeDetails.vue GestionRH.vue', 'domains/workforce/views')
assign('views/administration', 'Finances.vue', 'domains/billing/views')
assign('views/administration', 'Salles.vue', 'domains/scheduling/views')
assign('views/administration', 'AvisRetoursPatients.vue Notifications.vue', 'domains/communication/views')
assign('views/administration', 'Settings.vue Utilisateurs.vue', 'domains/settings/views')
assign('components/administration', 'UserForm.vue', 'domains/settings/components')
assign('services', 'patients.js patientsTourMock.js', 'domains/patient/services')
assign('services', 'consultations.js consultationsforms.js consultationsTourMock.js', 'domains/care-delivery/services')
assign('services', 'ficheMedicale.js', 'domains/clinical-record/services')
assign('services', 'caisseService.js caisseTourMock.js financeApi.js financesTourMock.js cabinetServices.js', 'domains/billing/services')
assign('services', 'rhManagementService.js corpsmedical.js adminTourMock.js', 'domains/workforce/services')
assign('services', 'salles.js', 'domains/scheduling/services')
assign('services', 'appreciationAdminService.js smsService.js smsTourMock.js vitrineService.js', 'domains/communication/services')
assign('services', 'printService.js', 'shared/print')
assign('services', 'clientErrorReport.js', 'shared/lib')
assign('stores', 'auth.js', 'domains/auth/stores')
assign('stores', 'patient.js', 'domains/patient/stores')
assign('stores', 'assurances.js paymentMethods.js', 'domains/billing/stores')
assign('stores', 'medecins.js', 'domains/workforce/stores')
assign('stores', 'notifications.js', 'domains/communication/stores')
assign('stores', 'realtime.js', 'shared/realtime')
assign('composables', 'usePatients.js usePatientDossier.js useDossierLayout.js', 'domains/patient/composables')
assign('composables', 'useConsultationsForm.js', 'domains/care-delivery/composables')
assign('composables', 'useFicheMedicaleAccess.js', 'domains/clinical-record/composables')
assign('composables', 'useConsumables.js useStockVariations.js', 'domains/inventory/composables')
assign('composables', 'useEmployeeDetails.js useEmployees.js usePayTabsDialog.js usePayrolls.js', 'domains/workforce/composables')
assign('composables', 'useFinances.js useInvoiceBillingActions.js', 'domains/billing/composables')
assign('composables', 'useEvents.js useFullCalendarResponsive.js useLeaves.js useRdvApi.js useRdvStatus.js useSalles.js', 'domains/scheduling/composables')
assign('composables', 'useNotifications.js useNotificationPresentation.js useSmsAdminSettings.js useSmsTopbarCredits.js', 'domains/communication/composables')
assign('composables', 'useBrowserNotifications.js useMercureNotifications.js webPushSession.js', 'shared/realtime')
assign('composables', 'useFocusRealtime.js', 'domains/focus/composables')
assign('composables', 'useProfile.js', 'domains/auth/composables')
assign('composables', 'usePrintProfile.js usePrinter.js', 'shared/print')
assign('composables', 'useRapports.js useRapportsPageState.js', 'domains/reporting/composables')
assign('composables', 'useUsers.js', 'domains/settings/composables')
assign('composables', 'useNavigationMenu.js useDashboards.js', 'app/composables')
assign('utils', 'assuranceUtils.js cabinetServiceInsurance.js factureRow.js invoiceModificationAccess.js patientDevis.js paymentMethodUtils.js', 'domains/billing/model')
assign('utils', 'employeeTypeUtils.js payrollGroups.js payrollUtils.js', 'domains/workforce/model')
assign('utils', 'consultationRow.js', 'domains/care-delivery/model')
assign('utils', 'formuleDentaireLayout.js', 'domains/clinical-record/model')
assign('utils', 'login-bg-animation.js', 'domains/auth/lib')
assign('utils', 'notificationLinks.js', 'domains/communication/lib')
assign('utils', 'crossTableDayPrint.js printDocumentStyles.js', 'shared/print')
assign('utils', 'reportOverview.js reportPrint.js', 'domains/reporting/model')
assign('utils', 'hubIcons.js navigationHome.js', 'app/lib')

originals = {p.relative_to(SRC).as_posix(): p.read_bytes() for p in SRC.rglob('*') if p.is_file()}
def destination(name):
    if name in exact:
        return exact[name]
    if name.startswith('views/') and name.count('/') == 1:
        return name.replace('views/', 'app/views/', 1)
    for prefix, target in prefixes.items():
        if name.startswith(prefix):
            return target + name[len(prefix):]
    return name
mapping = {name: destination(name) for name in originals}
assert len(set(mapping.values())) == len(mapping), 'Destination collision'

# Resolve actual files before rewriting, so URLs and package imports stay intact.
def resolve(name):
    for candidate in [name, name + '.js', name + '.mjs', name + '.vue', name + '/index.js']:
        if candidate in mapping:
            return candidate
    return None

quoted = re.compile(r'''(["'])([^"'\r\n]+)\1''')
def rewrite(text, old_location, new_location, in_source=True):
    def replace(match):
        value = match.group(2)
        if value.startswith('@/'):
            candidate = value[2:]
        elif value.startswith('/src/'):
            candidate = value[5:]
        elif value.startswith(('./', '../')):
            candidate = posixpath.normpath(posixpath.join(posixpath.dirname(old_location), value))
        else:
            return match.group(0)
        found = resolve(candidate)
        if not found:
            return match.group(0)
        target = mapping[found]
        if in_source:
            value = '@/' + target
        elif old_location == '../index.html':
            value = '/src/' + target
        else:
            value = posixpath.relpath(target, posixpath.dirname(new_location))
            if not value.startswith('.'):
                value = './' + value
        return match.group(1) + value + match.group(1)
    return quoted.sub(replace, text)

for old, data in originals.items():
    new = mapping[old]
    if Path(old).suffix in {'.vue', '.js', '.mjs', '.scss', '.css', '.json'}:
        data = rewrite(data.decode('utf-8-sig'), old, new).encode('utf-8')
    target = SRC / new
    target.parent.mkdir(parents=True, exist_ok=True)
    target.write_bytes(data)
for old, new in mapping.items():
    if old != new:
        (SRC / old).unlink()
for folder in sorted(SRC.rglob('*'), key=lambda p: len(p.parts), reverse=True):
    if folder.is_dir() and not any(folder.iterdir()):
        folder.rmdir()
for folder in [ROOT / 'tests', ROOT / 'scripts' / 'tests']:
    for test in folder.glob('*.mjs'):
        old = posixpath.relpath(test.as_posix(), SRC.as_posix())
        test.write_text(rewrite(test.read_text(encoding='utf-8-sig'), old, old, False), encoding='utf-8')
index = ROOT / 'index.html'
index.write_text(rewrite(index.read_text(encoding='utf-8-sig'), '../index.html', '../index.html', False), encoding='utf-8')
MANIFEST.write_text(json.dumps({old: new for old, new in mapping.items() if old != new}, indent=2, ensure_ascii=False) + '\n', encoding='utf-8')
print(f'Migrated {len(mapping)} source files; {sum(a != b for a, b in mapping.items())} relocated.')
