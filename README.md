# Field Service Resource Updater

Bulk-update Field Service **Bookable Resource** settings across many resources at once — set Start/End Location, Organizational Unit, Display on Schedule Board, Enable for Availability Search, Hourly Rate and Time Off Approval; apply work hours by weekly pattern or by copying another resource's calendar; and quick add/remove skills (characteristics) and territories for everyone selected. Preview every change before applying.

By Mark Christie.

One self-contained HTML app runs in three hosts:

- **Power Platform ToolBox (PPTB)** — dark theme, via `window.dataverseAPI`.
- **XrmToolBox** — modern light/dark theme, hosted in a WebView2 plugin.
- **Dynamics 365 web resource** — light theme, same-origin `fetch`.

The XrmToolBox host uses dark mode by default and includes a persistent Light/Dark toggle in the header. On wide windows, the workflow occupies the left two-thirds and a sticky Activity Log occupies the right third; the layout collapses to one column on narrower windows.

## What it does

Pick a set of bookable resources (Users by default), tick the settings you want to change, preview, and apply across the whole selection.

The resource list can be filtered by name, Resource Type, active/inactive status, one or more Organizational Units, one or more Owning Business Units, and the distinct scheduling Time Zones currently used by Bookable Resources. Active is the default. The two unit filters use compact checkbox dropdowns with Select all/Clear actions; choose the entries and click **Load resources**. For example, filter to Pacific Time, select all loaded resources, and apply one work-hours pattern; then repeat for Eastern Time with a different pattern. The grid shows each resource's status, Organizational Unit, and friendly Time Zone name so the filtered set can be reviewed before selection. Click any data-column heading to sort ascending or descending.

### Bookable Resource fields (bulk set)

| Setting | Column | Type |
| --- | --- | --- |
| Start Location | `msdyn_startlocation` | Choice (Resource Address / Org Unit Address / Location Agnostic) |
| End Location | `msdyn_endlocation` | Choice (same options) |
| Organizational Unit | `msdyn_organizationalunit` | Lookup → `msdyn_organizationalunit` |
| Display on Schedule Board | `msdyn_displayonscheduleboard` | Yes/No |
| Enable for Availability Search | `msdyn_displayonscheduleassistant` | Yes/No |
| Hourly Rate | `msdyn_hourlyrate` | Number |
| Time Off Approval Required | `msdyn_timeoffapprovalrequired` | Yes/No |

Only ticked settings are written; everything else is left untouched.

**Location readiness checks.** Start/End Location are validated before you apply:

- **Location Agnostic** must be set on *both* Start and End, or neither (Field Service rejects a mismatch) — checked per resource, and a contradictory pair blocks Apply.
- **Resource Address** needs a geocoded address on the resource's related User/Contact/Account record. The grid's **Address** column shows `✓ geo` / `no geo` / `⚠ none` per resource, and the preview warns how many selected resources aren't ready.
- **Organizational Unit Address** needs the org unit to have an address — org units with none are flagged `⚠ no address` in the Organizational Unit dropdown, and the preview warns if selected resources would use one (or have no org unit).

### Work hours

A resource's work hours are a **calendar** (recurring rules), not a column, so the tool uses the Field Service work-hour calendar APIs (`msdyn_SaveCalendar` / `msdyn_DeleteCalendar` / `msdyn_LoadCalendars`). Two modes:

- **Weekly pattern** — choose working days + start/end times, an optional daily break, capacity and time zone. Applied as a weekly recurrence to every selected resource.
- **Copy from a resource** — pick one resource as the source; the tool reads its calendar for a representative week and recreates that weekly pattern (working hours + breaks, in UTC) on every selected resource. Irregular per-date overrides on the source are not copied.

Tick **Clear existing working hours first (preserve exceptions)** to replace the prior working coverage before applying the new pattern. The tool first creates a temporary 24x7 V2 working recurrence, allowing Dataverse's overlap logic to displace old rank-0 working recurrences while preserving higher-priority time off and holiday exceptions. It then deletes only the newly returned temporary inner-calendar ID and saves the requested pattern. No pre-existing calendar ID is directly edited or deleted.

### Quick actions — skills & territories

- **Skill (characteristic)** — add a characteristic to everyone selected (optionally with a rating value), or remove it. Backed by `bookableresourcecharacteristic`.
- **Territory** — add everyone selected to a territory, or remove them. Backed by `msdyn_resourceterritory`.
- **New Mobile Experience** — enable/remove the refreshed Field Service mobile UX for the selected **User** resources by assigning/removing the **Field Service – New Mobile Experience** security role on their system users (business-unit matched; non-User resources are skipped). The environment-level toggle is managed separately in the Field Service Mobile app settings.

Add is idempotent (resources that already have the skill/territory/role are skipped). Remove asks you to click twice to confirm.

## Using it

### Power Platform ToolBox

1. Install from the Tool Registry, or load locally via the Debug Menu after `npm run build`.
2. Open the tool, pick a connection, **Load resources**, choose settings, **Preview**, then **Apply to selected**.

### XrmToolBox

1. Install from the Tool Library (search "Field Service Resource Updater"), or build and run `xrmtoolbox/install.ps1`.
2. Connect with an **OAuth/MFA** connection (the WebView2 host forwards that bearer token to the Web API).
3. Open the tool from the Tools list.

### Dynamics 365 web resource

Upload `field-service-settings-updater.html` as an HTML web resource; it authenticates with the signed-in session.

## Build

```
npm run build          # copies the single-file app + icon into dist/ and the XTB app/ folder
npx pptb-validate      # validate the PPTB manifest before publishing
```

For the XrmToolBox plugin:

```
cd xrmtoolbox
node make-icons.js                              # (re)generate tile icons + logo.png
cd FieldServiceSettingsUpdater
dotnet build -c Release
..\install.ps1                                  # copy dll + app into the XrmToolBox Plugins folder
```

## Notes & limits

- **Permissions** — applying changes requires write access to Bookable Resource and the related tables, plus the OwnCalendar privilege for editing a User-type resource's calendar.
- **No automatic undo** — preview shows exactly what will change; review before applying. Work-hours "clear first" deletes existing calendar rules.
- **Copy work hours** reconstructs a *weekly* pattern from one sample week, so standard recurring schedules copy faithfully; one-off date exceptions do not.

MIT licensed. See [LICENSE](LICENSE).
