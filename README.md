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

The resource list can be filtered by name, Resource Type, active/inactive status, one or more Organizational Units, one or more Business Units, the distinct scheduling Time Zones currently used by Bookable Resources, Region/Territory, and related-record Country. Active is the default. The two unit filters use compact checkbox dropdowns with Select All/Clear actions; choose the entries and click **Load Resources**. The Resource grid provides Select All, Clear All, and Invert Selection actions. Select or clear one row and then Shift-click another row to apply that choice to the entire contiguous range. The grid shows each resource's status, Organizational Unit, friendly Time Zone name, and Country so the filtered set can be reviewed before selection. Click any data-column heading to sort ascending or descending.

### Bookable Resource fields (bulk set)

| Setting | Column | Type |
| --- | --- | --- |
| Start Location | `msdyn_startlocation` | Choice (Resource Address / Org Unit Address / Location Agnostic) |
| End Location | `msdyn_endlocation` | Choice (same options) |
| Organizational Unit | `msdyn_organizationalunit` | Lookup → `msdyn_organizationalunit` |
| Time Zone | `timezone` | Time-zone code from Dataverse `timezonedefinition` |
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

New weekly patterns and the temporary replacement overlay use `2000-01-01T00:00:00Z` as their historical recurrence anchor. The source-calendar copy reader retains its 2024 sample window so schedules created with either the legacy or current anchor remain readable.

**Preview Work Hours** also loads the selected resources' current working rules into a selectable grid showing Resource, Type, Date/Time, Current Time Zone, and Capacity. Every column is sortable. A resource is included once when any of its displayed working-rule rows is selected; resources without existing work hours remain available through a placeholder row.

### Calendar time-zone normalization

For the selected resources, preview and bulk-normalize the time zone on **Working Hours**, **Non-Working Hours**, and/or **Time Off** calendar rules. The operation changes the calendar rule's time-zone code while preserving its displayed wall-clock values (for example, 8:00 AM–5:00 PM remains 8:00 AM–5:00 PM in the target zone). Working-hour breaks follow the working rule tree; standalone holiday/business-closure rules are excluded from the Time Off option.

Time Off can optionally be converted to **All Day** while retaining the event date and reason. Preview reports the affected rule counts per resource before Apply is enabled. This is useful after filtering and selecting all resources assigned to a particular Bookable Resource time zone.

The normalization preview grid supports ascending and descending sorting on every displayed column without changing the selected rules.

### Delete calendar rules

The collapsible **Delete Calendar Rules** section loads deletable inner-calendar rules for the selected resources into a sortable grid. Rules can be selected individually or with Select All/Clear. Deletion uses the supported `msdyn_DeleteCalendar` action, requires a preview, and requires a second confirmation click because deleted calendar rules cannot be recovered by the tool.

### Quick actions — skills & territories

- **Skill (characteristic)** — add a characteristic to everyone selected (optionally with a rating value), or remove it. Backed by `bookableresourcecharacteristic`.
- **Territory** — add everyone selected to a territory, or remove them. Backed by `msdyn_resourceterritory`.
- **New Mobile Experience** — enable/remove the refreshed Field Service mobile UX for the selected **User** resources by assigning/removing the **Field Service – New Mobile Experience** security role on their system users (business-unit matched; non-User resources are skipped). The environment-level toggle is managed separately in the Field Service Mobile app settings.

Add is idempotent (resources that already have the skill/territory/role are skipped). Remove asks you to click twice to confirm.

### Activity log

The Activity Log is displayed as Timestamp, Resource, and Message columns. Resource-specific operations are grouped by friendly resource name, while general application messages leave Resource blank. **Export Logs** writes the currently displayed entries to CSV using the same three-column format.

### Export calendar rules

The **Export Calendar Rules** section exports flattened parent and inner calendar rules for the resources selected in Section 1. Working Hours, Non-Working Hours, Time Off, and Holidays/Exceptions can be included independently. Calendar reads use configurable bounded parallelism (six resources at a time by default) with transient retry handling, making large exports substantially faster without issuing all requests at once. The CSV contains resource, Organizational Unit, Owning Business Unit, Region/Territory, Country, resource time zone, parent/inner calendar IDs, rule level and type, effort, duration, rank, variation, times, offsets, patterns, names, descriptions, and all additional scalar rule fields returned by Dataverse. Export filenames begin with `Exported Calendar Rules -` followed by a UTC timestamp. Every CSV header uses PascalCase without spaces or punctuation, including dynamically returned `Rule` properties.

Read-only Work Hours and Calendar Time Zone previews use up to six parallel resource readers. Work Hours and Calendar Time Zone updates use up to four parallel resource workers while preserving sequential operations within each individual calendar. The application header remains fixed while scrolling and displays the busy spinner plus a **Cancel Current Action** button. Cancellation aborts active browser requests where supported and otherwise stops cooperatively before the next resource or rule; changes completed before cancellation are not rolled back. Calendar-rule deletion remains sequential to avoid conflicting destructive operations.

In the XrmToolBox host, a Dataverse `401` response triggers an in-place OAuth token refresh through the active `ServiceClient`. Concurrent failed requests share one refresh operation and retry once with the new token, allowing long-running exports and calendar updates to continue without reloading the tool or losing progress. A second `401` is reported normally because it may indicate a permissions or connection problem rather than token expiration.

### Calendar Time Zone mismatch audit

The **Calendar Time Zone Mismatch Audit** compares each selected Bookable Resource's Time Zone with its Working Hours, Non-Working Hours, and Time Off rule trees. It displays only mismatches in a selectable, sortable grid with the resource Time Zone, current calendar-rule Time Zone, entry type, date/time, and name/reason. Results can be exported to a timestamped CSV or repaired in bulk so every selected rule matches its own resource. Repairs preserve displayed wall-clock values and existing Time Off reasons, process separate resources in parallel, and keep updates within each resource calendar sequential. After a partial repair, successfully fixed rows are removed while untouched and failed rows remain available for additional selections without rescanning every resource.

### Work Hours baseline audit

The **Work Hours Baseline Audit** checks selected resources against a configurable expected weekly recurrence. Before previewing, choose the baseline working days, start/end times, and event start date; the defaults are Monday–Friday, 8:00 AM–5:00 PM, starting `2000-01-01`. It flags missing or multiple Working Hours recurrences, unexpected weekend coverage, 24-hour/all-day schedules, and days, times, or recurrence start dates that differ from the configured baseline. Exceptions appear in a selectable, sortable grid and can be exported to CSV with the captured baseline values. **Apply Baseline To Selected** replaces only Working Hours coverage for the chosen resources, preserves higher-priority Time Off and holiday exceptions, and uses each Bookable Resource's own Time Zone.

The fixed application header includes **Reset Tool**, which restores the interface to its initial state and clears filters, searches, loaded results, selections, previews, and Activity Log entries. The saved Light/Dark theme preference is retained. Reset is blocked while a long-running operation is active so the user can cancel or finish it first.

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
