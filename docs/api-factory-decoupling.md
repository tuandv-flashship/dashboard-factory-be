# Dashboard Factory — API Documentation (Post-Decoupling)

> **Last updated:** 2026-04-09
> **Breaking changes:** Yes — `factory`, `is_shared`, `dtf1`, `dtf2` fields are removed.

## 1. Architecture Overview

The system has been decoupled from **1 shared database** into **2 independent deployments**.

```
┌──────────────────────┐     ┌──────────────────────┐
│  fls-api.example.com │     │  pd-api.example.com  │
│  FACTORY=FLS         │     │  FACTORY=PD          │
│  DB: dashboard_fls   │     │  DB: dashboard_pd    │
└──────────┬───────────┘     └──────────┬───────────┘
           │                            │
      ┌────▼────┐                 ┌─────▼───┐
      │  DB FLS │                 │  DB PD  │
      └─────────┘                 └─────────┘
```

- **Same codebase, different environment:** Both instances run identical code.
- **Factory context:** Determined by `FACTORY` env variable (not by URL or header).
- **Data isolation:** Each instance only sees its own data. No cross-factory queries.

---

## 2. Breaking Changes for FE

### 2.1 Removed Fields

| Field | Where | Replacement |
|-------|-------|-------------|
| `factory` | Department API response | `group` (see §4) |
| `is_shared` | ProductionLine API response | Removed (all lines are factory-specific) |
| `dtf1`, `dtf2` | All line codes, route params | `dtf`, `dtg`, `pack_ship` |

### 2.2 Line Code Changes

| Old | New | Factory |
|-----|-----|---------|
| `dtf1` | `dtf` | FLS + PD |
| `dtf2` | _(removed)_ | — |
| `dtg` | `dtg` | PD only |
| _(new)_ | `pack_ship` | PD only |

### 2.3 Department Code Changes

| Old | New | Line | Group |
|-----|-----|------|-------|
| `dtg_print` | `apollo`, `atlas_01`, `atlas_02` | `dtg` | `dtg_print` |
| `pick` (DTG) | `pick_dtg` | `dtg` | `null` |
| All others | Unchanged | — | `null` |

---

## 3. Data Structures per Factory

### 3.1 FlashShip (FLS)

```
DTF line
 ├── Print       (code: print)
 ├── Pick        (code: pick)
 ├── Cut         (code: cut)
 ├── Mock Up     (code: mockup)
 └── Pack & Ship (code: pack_ship)
```

### 3.2 PrintDash (PD)

```
DTF line
 ├── Print       (code: print)
 ├── Pick        (code: pick)
 ├── Cut         (code: cut)
 └── Mock Up     (code: mockup)

DTG line
 ├── Pick DTG    (code: pick_dtg,  group: null)
 ├── Apollo      (code: apollo,    group: dtg_print)  ← grouped
 ├── Atlas-01    (code: atlas_01,  group: dtg_print)  ← grouped
 └── Atlas-02    (code: atlas_02,  group: dtg_print)  ← grouped

Pack & Ship line
 └── Pack & Ship (code: pack_ship)
```

---

## 4. Department `group` Field

### Purpose
The `group` field allows FE to visually group related departments under a common header.

### Values

| `group` value | Meaning | Departments |
|---------------|---------|-------------|
| `null` | Standalone department | Print, Pick, Cut, Mock Up, Pack & Ship, Pick DTG |
| `"dtg_print"` | Grouped under "DTG Print" | Apollo, Atlas-01, Atlas-02 |

### FE Implementation Example

```javascript
// Group departments for display
const departments = response.data.departments.data;

const grouped = {};
const standalone = [];

departments.forEach(dept => {
  if (dept.group) {
    if (!grouped[dept.group]) {
      grouped[dept.group] = { label: 'DTG Print', items: [] };
    }
    grouped[dept.group].items.push(dept);
  } else {
    standalone.push(dept);
  }
});

// Render: standalone departments as individual cards
// Render: grouped departments under a collapsible "DTG Print" header
```

---

## 5. API Endpoints

### 5.1 Production Lines

#### `GET /v1/production/lines`

Returns all active production lines with departments.

**Headers:** `Authorization: Bearer {token}`

**Response (FLS):**
```json
{
  "data": [
    {
      "id": "hashed_id",
      "code": "dtf",
      "label": "DTF",
      "color": "#f59e0b",
      "subtitle": "Building 1",
      "sort_order": 1,
      "is_active": true,
      "departments": {
        "data": [
          {
            "id": "hashed_id",
            "code": "print",
            "label": "In ấn",
            "label_en": "Print",
            "icon": "Printer",
            "unit": "file",
            "kpi_per_hour": 130,
            "group": null,
            "sort_order": 1,
            "is_active": true,
            "can_increase_productivity": true
          }
        ]
      }
    }
  ]
}
```

**Response (PD):**
```json
{
  "data": [
    {
      "id": "hashed_id",
      "code": "dtf",
      "label": "DTF",
      "color": "#14b8a6",
      "departments": { "data": ["...4 departments"] }
    },
    {
      "id": "hashed_id",
      "code": "dtg",
      "label": "DTG",
      "color": "#8b5cf6",
      "departments": {
        "data": [
          { "code": "pick_dtg", "group": null, "label_en": "Pick DTG" },
          { "code": "apollo",   "group": "dtg_print", "label_en": "Apollo" },
          { "code": "atlas_01", "group": "dtg_print", "label_en": "Atlas-01" },
          { "code": "atlas_02", "group": "dtg_print", "label_en": "Atlas-02" }
        ]
      }
    },
    {
      "id": "hashed_id",
      "code": "pack_ship",
      "label": "Pack & Ship",
      "color": "#ec4899",
      "departments": {
        "data": [
          { "code": "pack_ship", "group": null, "label_en": "Pack & Ship" }
        ]
      }
    }
  ]
}
```

---

#### `GET /v1/production/lines/{line}`

Get production summary for a specific line.

**Params:** `line` = production line code (`dtf`, `dtg`, `pack_ship`)

**Response:** Summary with KPI stats per department.

---

#### `GET /v1/production/lines/{line}/departments/{dept}`

Get hourly detail for a specific department.

**Params:**
- `line` = production line code (`dtf`, `dtg`, `pack_ship`)
- `dept` = department code (`print`, `pick`, `cut`, `mockup`, `pack_ship`, `pick_dtg`, `apollo`, `atlas_01`, `atlas_02`)

**Optional query:**
- `date` — ISO date (default: today)
- `shift_number` — 1 or 2

---

### 5.2 Alerts

#### `GET /v1/alerts`

Returns unresolved alerts, optionally filtered by line.

**Query params:**
- `line` — (optional) e.g. `dtf`, `dtg`, `pack_ship`. Also includes `line=all` alerts.

---

### 5.3 Machines

#### `GET /v1/machines/{line}`

Returns active machines for a production line.

**Params:** `line` = `dtf`, `dtg`, or `pack_ship`

---

### 5.4 Reason Codes

#### `GET /v1/reason-codes`

Get KPI miss reason codes, optionally filtered by context.

**Query params:**
- `line` — (optional) filters machine sub-items by production line code
- `dept` — (optional) filters material sub-items by department code

**Validation:** `line` and `dept` are validated against the database (`exists:production_lines,code` / `exists:departments,code`).

---

### 5.5 Orders

#### `GET /v1/orders/summary`

Returns order summary per shift. Line codes in response match the new structure (`dtf`, `dtg`, `pack_ship`).

---

## 6. Postman Environment Variables

### FLS Instance
```
API_URL = https://fls-api.example.com/v1
```

### PD Instance
```
API_URL = https://pd-api.example.com/v1
```

### Local Development (HTTP)
```
API_URL_FLS = http://localhost:8000/v1
API_URL_PD  = http://localhost:8001/v1
```

### Local Development (HTTPS via Caddy)
```
API_URL_FLS = https://api-dashboard-fls.local:2443/v1
API_URL_PD  = https://api-dashboard-pd.local:2443/v1
```

---

## 7. Migration Checklist for FE

- [ ] Remove all references to `dtf1` and `dtf2` — replace with `dtf`
- [ ] Remove all references to `is_shared` field
- [ ] Replace `factory` field reads with `group` field
- [ ] Replace `dtg_print` single-department logic with 3 separate departments (`apollo`, `atlas_01`, `atlas_02`) grouped by `group === 'dtg_print'`
- [ ] Add support for `pick_dtg` department code (was `pick` under DTG line)
- [ ] Add support for `pack_ship` as a production line code (PD only)
- [ ] Update Postman environment to use separate base URLs per factory
- [ ] Use `group` field to render department grouping UI (see §4)
