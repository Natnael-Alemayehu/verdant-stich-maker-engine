# Verdant Stich - Maker Progress & Rewards Engine

A custom WordPress plugin that powers The Verant Stich's DIY embroydery subscription box brand with a full-featured REST API for customer progress tracking, milestone photo submissions, mastery scoring, and automatic WooCommerce discount generation.

---

## Feature

|Area|Detail|
|---|---|
|**Custom REST API**|8 endpoints under `/wp-json/verdant/v1/`|
|**Maker Profiles**|Per-user kit portfolio with step-level progress & status transitions|
|**Mastery Engine**|Score formula: `(difficulty x 100) x min(2, difficulty x 100 / days)`|
|**Milestone Images**|URL-based photo submissions stored per kit|
|**WooCommerce Bridge**|Auto-generates personalised `VERDANT_{userID}_L{level}`coupons|
|**Admin Dashboard**|Leadership of top makers, level theshold table|
|**API Tester**|In-admin interactive GET/POST demo panel|
|**Security**|Application Passwords (WP 5.6+), ownership enforcement, input sanitization|

---

## Requirements
- WordPress 6.0+
- PHP 8.0+
- WooCommerce 7.0+ *(optional - plugin works without it, discounts are skipped)*

---

## Installation

1. Download or clone this repository
2. Zip the `verdant-stich-maker-engine/` folder.
3. In WordPress Admin -> Plugins -> Add New -> Upload Plugin.
4. Active **The Verdant Stich - Maker Progress & Rewards Engine**.
5. Custom database tables are created automatically on activation.

---

## Authentication

All protected endpoints user **WordPress Application Passwords** (HTTP Basic Auth).

1. Go to **Users -> Your Profile -> Application Passwords**.
2. Enter a name (e.g. `Mobile App`) and clickc **Add New Application Password**.
3. Copy the generated password (shown once).
4. In Postman / your app, set **Basic Auth** with:
    - Username: your WordPress username
    - Password: the application password (spaces included)

> JWT Authentication is also supported if you install a compatible JWT plugin; the endpoints use standard WP authentication filters.

---

## API Reference

### Base URL
```
https://your-site.com/wp-json/verdant/v1
```

### Endpoints

#### `GET /progress`
Retrive the authenticated user's full maker profile.

**Query params:**
|Param|Type|Description|
|---|---|---|
|`user_id`|int|*(Admin only)Fetch profile for another user.|

**Response `200`:**
```json
{
    "success": true, 
    "data": {
        "user_id": 42,
        "display_name": "Jane Doe",
        "email": "jane@doe.com",
        "mastery": {
            "score": 840.0,
            "level": 3,
            "level_label": "Botanist",
            "total_completed": 4
        },
        "kits": [...]
    }
}
```

#### `POST /progress/kit`
Register a new kit for the current user.

**Body (JSON):**
```json
{
    "kit_id": "VS-OCT-2026",
    "kit_name": "October Wildflower Box",
    "difficulty": 3,
    "total_steps": 10
}
```

|Field|Type|Required|Notes|
|---|---|---|---|
|`kit_id`|string|OK|Unique product SKU|
|`kit_name`|string|OK|Display name|
|`difficulty`|int|+ default 1 |1= Begineer, 2=Intermediate, 3=Advanced, 4=Master|
|`total_steps`|int|+ default 10 |1-100|

**Response `201`:** Created kit object. 

---

#### `POST /progress/{id}/steps`
Update step completion for a kit.
**Body (JSON):**
```json
{
    "completed_steps": 4,
    "note": "Finished the step section today"
}
```
> `completed_steps` is the **total** completed count, not a delta.

**Staus transitions (automatic):**
- `not_started` -> `in_progress` when `completed_steps > 0`
- `in_progress` -> `completed` when `completed_steps >= total_steps`

**Response `200`:** Updated kit object.

---

#### `POST /progress/{id}/images`
Store a milestone image URL.
**Body (JSON):**
```json
{
    "image_url": "https://cdn.example.com/step12345.jpg",
    "step_number": 4,
    "caption": "Almost there!"
}
```

**Response `201`:** `{"success": true, "image_id":7}`

---

#### `GET /mastery`
Get the current mastery score, level, and active discount coupon.

**Response `200`:**
```json
{
    "success": true,
    "data":{
        "score": 840.0,
        "level": 3,
        "label": "Botanist",
        "total_completed": 4,
        "coupon": {
            "coupon_code": "VERDANT_42_L3",
            "discount_pct": 12,
            "expiry_date": "2025-05-12",
            "wc_available": true
        }
    }
}
```

---

#### `POST /mastery/recalculate`
Force a fresh mastery score calculation from all completed kits.

---

#### `GET /levels` *(public - no auth)*
Returns the full mastery tier table.

---

## Mastery Score Algorithm

For each **completed** kit:
```
base_point  = difficulty x 100
days_to_complete = max(1, ceil(completed_at - started_at) / 86400 )
speed_multiplier = min(2.0, base_points/days_to_complete)
kit_score = base_points x speed_multiplier
```

**Toal Mastery Score = (SUMMATION)kit_scores**
|Level|Title|Min Score|Discount|
|---|---|---|---|
|0|Seedling|0|0%|
|1|Sprout|200|5%|
|2|Bloom|500|8%|
|1|Botanist|900|12%|
|1|Grand Maestro|2,000|20%|

---

## WooCommerce Integration

When a user's mastery level changes, the plugin automcatically:
1. Creates (or updates) a personalised WooCommerce Coupon: `VERDANT_{user_id}_L{level}`
2. Sets the coupon type to **percentage discount** matching the tier.
3. Restricts the coupon to the user's email address.
4. Sets and expity of 30 days (configurable in Settings).
5. Stores the coupon code in user meta(`_Verdant_coupon_code`).

---

## HTTP Status Code
|Code|Meaning|
|---|---|
|`200`|OK-Successful read or update|
|`201`|Created-new resource created|
|`400`|Bad Request - invalid parameters|
|`401`|Unauthorized - not authenticated|
|`403`|Forbidden - authenticated but not allowed|
|`404`|Not Found - resource doesn't exist or you don't own it|
|`409`|Conflict - e.g.updating an already-completed kit|
|`500`|Server Error - database failure|

---

## Database Tables
|Table|Purpose|
|---|---|
|`wp_verdant_kits`|Kit rows per user(status, difficulty, step counts, timestamps)|
|`wp_verdant_progress_history`|Timestamped log of every step update|
|`wp_verdant_milestone_images`|Stored image URLs per kit|
|`wp_verdant_mastery_scores`|Demormalised mastery score cache per user|

All tables are indexed on `user_id` and relevant foreign keys for perfornamce. 

---

## Testing
Set the following collection variables:
|Variable|Value|
|---|---|
|`base_url`|Your WordPress site URL|
|`wp_username`|Your WP admin username|
|`wp_app_password`|Application Password from user profile|

The collection includes a full workflow from kit creation to mastery recalculation, plus error scenario tests ()401,400,404.

Alternatively, use the built-in **API Tester** in WordPress Admin -> Verdant Stitch -> API Tester for a no-setup test environment.

---