# Architecture

## One source file, three hosts

The entire tool is a single self-contained HTML file, [`field-service-settings-updater.html`](../field-service-settings-updater.html) — no bundler, no runtime CDN (CSP-friendly). `build.js` copies it into `dist/index.html` (for PPTB) and into `xrmtoolbox/FieldServiceSettingsUpdater/app/` (for the WebView2 plugin).

Host detection (top of the file) sets `document.body.dataset.host` and the default theme:

```js
const PPTB = !!window.dataverseAPI;   // Power Platform ToolBox  → Fluent 2 dark
const XTB  = !!window.XTB_CONFIG;     // XrmToolBox WebView2      → Fluent 2 light
// else: D365 web resource / standalone → Fluent 2 light
```

## Data access layer

All Dataverse access goes through a thin host-agnostic layer so the rest of the code is identical across hosts:

| Helper | PPTB | XrmToolBox / web resource |
| --- | --- | --- |
| `dvQuery` / `dvQueryAll` | `dataverseAPI.queryData` | `fetch` GET (+ `@odata.nextLink` paging) |
| `dvCreate` | `dataverseAPI.create` | `fetch` POST → `OData-EntityId` |
| `dvUpdate` | `dataverseAPI.update` | `fetch` PATCH |
| `dvDelete` | `dataverseAPI.delete` | `fetch` DELETE |
| `dvClearLookup` | `update` with `@odata.bind: null` | `fetch` DELETE on `…/{nav}/$ref` |
| `dvAction` | `dataverseAPI.execute({operationType:'action'})` | `fetch` POST to the unbound action |

- **PPTB** — the host owns auth + instance; calls go through `window.dataverseAPI`.
- **XrmToolBox** — the [`SettingsControl`](../xrmtoolbox/FieldServiceSettingsUpdater/SettingsControl.cs) WebView2 plugin injects `window.XTB_CONFIG = { baseUrl, token }` from the active connection's OAuth bearer token (`ConnectionDetail.ServiceClient.CurrentAccessToken`); the page calls the Web API directly.
- **Web resource** — same-origin `fetch` authenticated by the session.

## Bulk field update

`collectFieldChanges()` reads the ticked field rows and builds a single PATCH body (choices as ints, two-options as booleans, the org-unit as an `@odata.bind`, or a deferred lookup-clear). `applyAll()` iterates the selected resources, PATCHing each independently so one failure never blocks the rest, then refreshes the grid.

## Work hours

Work hours are calendar rules edited via the Field Service work-hour calendar APIs (see Microsoft Learn: *Edit work hour calendars by using APIs*).

- **Save** — `msdyn_SaveCalendar` with a stringified `CalendarEventInfo`:
  `{ CalendarId, EntityLogicalName:'bookableresource', TimeZoneCode, UseV2:true, ResourceId, RulesAndRecurrences:[{ Rules:[…], RecurrencePattern:'FREQ=WEEKLY;INTERVAL=1;BYDAY=…' }] }`.
  `Rules` carry `StartTime`/`EndTime` (ISO, anchored on the Monday `2024-01-01`), `WorkHourType` (0 working, 1 break) and `Effort` (capacity).
- **Clear** — read `calendars({id})?$expand=calendar_calendar_rules($select=innercalendarid)`, then `msdyn_DeleteCalendar` per distinct `innercalendarid`.
- **Copy from a resource** — `msdyn_LoadCalendars` for one representative week (`2024-01-01`–`2024-01-08`), group the returned slots by UTC weekday, collapse identical day-signatures into `BYDAY` groups (gaps between working slots become breaks), then `msdyn_SaveCalendar` per group with `TimeZoneCode 92` (UTC) so absolute times are preserved.

`CalendarId` comes from the resource's `_calendarid_value` (selected when resources are loaded); `ResourceId` is the bookable resource id, required for OwnCalendar privilege checks on User-type resources.

## Skills & territories

- **Skill** — `bookableresourcecharacteristic` linking `Resource` → bookableresource, `Characteristic` → characteristic, optional `RatingValue` → ratingvalue. Add checks for an existing link first (idempotent); remove queries by `_resource_value`/`_characteristic_value` and deletes the matches.
- **Territory** — `msdyn_resourceterritory` linking `msdyn_Resource` → bookableresource and `msdyn_Territory` → territory, same add/remove pattern.

## Accessibility

WCAG 2.1 AA in both themes: real semantics (`<button>`, `<label for>`, `<table>`, `fieldset`/`legend`), visible focus in every theme, `aria-live` status/log regions, AA contrast (every Fluent 2 light and dark token pair), and `prefers-reduced-motion` honoured. Destructive removes use a host-safe click-twice-to-confirm (no reliance on `window.confirm`, which is unreliable in sandboxes).
