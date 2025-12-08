# Markup by Attribute for WooCommerce Test Plan

## Comprehensive Regression Test Plan

**Version:** 1.1
**Last Updated:** 2025-01-29
**Test Strategy:** Risk-Based Pairwise Testing

---

## 📋 Table of Contents

1. [Executive Summary](#executive-summary)
2. [Testing Strategy Overview](#testing-strategy-overview)
3. [Setting Categories](#setting-categories)
4. [Test Execution Phases](#test-execution-phases)
5. [Detailed Test Steps Reference](#detailed-test-steps-reference)
6. [Test Execution Checklist](#test-execution-checklist)
7. [Appendix: Risk Analysis](#appendix-risk-analysis)

---

## Executive Summary

### The Challenge

The plugin has **8 settings** with multiple options, creating 96+ possible combinations. Testing all combinations across 17 test steps with multiple currency formats and WordPress/WooCommerce versions would require **1,600+ individual test executions** - impractical for regular regression testing.

### The Solution

Through risk-based analysis and understanding which settings truly interact vs. those that are independent, this plan reduces testing to:

- **145 tests** for standard regression (91% reduction!)
- **159 tests** for major releases with version compatibility
- **~4.5 hours** execution time for full regression suite

### Key Insights

1. **Only 3 settings affect calculation logic** - the rest are display formatting
2. **Display settings can be tested independently** with simple "toggle & check" validation
3. **Support forum analysis** identified decimal/rounding as the highest-risk area
4. **Price increase/decrease steps are redundant** - they test the same code paths as setting prices

---

## Testing Strategy Overview

### Testing Philosophy

This plan follows **pairwise testing** principles, which research shows catches **70-90% of bugs** while testing far fewer combinations than exhaustive testing. The key insight: most bugs occur from interactions between 2 settings, not from specific combinations of all settings.

### Risk-Based Prioritization

Settings are categorized by:

- **CALCULATION** - Affects how prices are computed (MUST test all combinations)
- **DISPLAY FORMATTING** - Only affects text output (test independently)
- **BINARY FLAGS** - Simple ON/OFF features (quick validation)

### Support Forum Intelligence

Analysis of the WordPress.org support forum revealed:

- 🔴 **CRITICAL**: Decimal/rounding issues
- 🔴 **HIGH**: Price calculation failures
- 🟡 **MEDIUM**: Display formatting edge cases
- 🟢 **LOW**: Repricing "issues" (mostly user error/documentation)

---

## Setting Categories

### Group A: CALCULATION Settings 🧮

**These settings affect HOW prices are calculated and must be tested together.**

| Setting                        | Options  | Impact                                                                       |
| ------------------------------ | -------- | ---------------------------------------------------------------------------- |
| **Sale Price Markup**    | ON / OFF | When ON, markup applies to sale prices; when OFF, sale prices are used as-is |
| **Round Markup**         | ON / OFF | When ON, calculated markup values are rounded (e.g., $1.237 → $1.24)        |
| **Preserve Zero Prices** | ON / OFF | When ON, products with $0 price remain $0 even with markup applied           |

**Total Combinations:** 2 × 2 × 2 = **8 configurations**

#### Configuration Matrix

| Config           | Sale Price Markup | Round Markup | Preserve Zero | Priority    | Test Focus                              |
| ---------------- | ----------------- | ------------ | ------------- | ----------- | --------------------------------------- |
| **CALC-1** | OFF               | OFF          | OFF           | 🟢 BASELINE | Default behavior, no special features   |
| **CALC-2** | ON                | OFF          | OFF           | 🔴 HIGH     | Sale price calculation logic            |
| **CALC-3** | OFF               | ON           | OFF           | 🔴 CRITICAL | Rounding logic (forum-identified risk!) |
| **CALC-4** | OFF               | OFF          | ON            | 🟡 MEDIUM   | Zero price preservation                 |
| **CALC-5** | ON                | ON           | OFF           | 🔴 CRITICAL | Sale + rounding interaction             |
| **CALC-6** | ON                | OFF          | ON            | 🟡 MEDIUM   | Sale + zero interaction                 |
| **CALC-7** | OFF               | ON           | ON            | 🟡 MEDIUM   | Rounding + zero interaction             |
| **CALC-8** | ON                | ON           | ON            | 🔴 CRITICAL | All features enabled (max complexity)   |

**Priority Legend:**

- 🔴 **CRITICAL/HIGH** - Tests core calculation logic or forum-identified issues
- 🟡 **MEDIUM** - Tests feature interactions
- 🟢 **BASELINE** - Tests default behavior (all features OFF)

---

### Group B: DISPLAY FORMATTING Settings 🎨

**These settings only affect text output - they do NOT interact with price calculations.**

These can be tested with simple "set option → reapply markup → verify output" workflows.

| Setting                                  | Level     | Options                             | What It Controls                                 |
| ---------------------------------------- | --------- | ----------------------------------- | ------------------------------------------------ |
| **Variation Description Behavior** | Product   | Replace / Append / Replace-NoMarkup | How markup text appears in variation description |
| **Include Attribute Names**        | Product   | ON / OFF                            | Show "Size: Large (+$5)" vs "Large (+$5)"        |
| **Hide Base Price**                | Product   | ON / OFF                            | Show/hide base product price in description      |
| **Add Markup to Name**             | Attribute | ON / OFF                            | Include markup in attribute term name            |
| **Add Markup to Description**      | Attribute | ON / OFF                            | Include markup in attribute term description     |

**Testing Approach:**

- Use existing product from calculation tests
- Toggle each setting ON/OFF
- Reapply markup (no need to recreate products!)
- Verify text output matches expected format

---

### Group C: BINARY FLAGS 🚩

**Simple feature toggles with no interaction with other settings.**

| Setting                          | Options  | What It Does                                                               |
| -------------------------------- | -------- | -------------------------------------------------------------------------- |
| **Do Not Overwrite Theme** | ON / OFF | When ON, preserves theme CSS styling; when OFF, uses plugin default styles |

**Testing Approach:** Visual inspection of styling in frontend

---

## Test Execution Phases

### Phase 0: Attribute Setup (One-Time)

**Purpose:** Create reusable test attributes that can be shared across all configuration tests.

**Steps:**

1. Delete any existing test attribute (confirm deletion in database)
2. Create new attribute named "Balderdash" (global attribute)
3. Create 5 terms with different markup types:
   - `Zoom` - Positive percentage markup (e.g., +20%)
   - `Pantless` - Positive fixed markup (e.g., +$10.00)
   - `Flibbertigibbet` - Negative percentage markup (e.g., -15%)
   - `Widdershins` - Negative fixed markup (e.g., -$5.00)
   - `Cattywampus` - No markup (blank)

**Execution Count:** 3 steps (done once)
**Time Estimate:** 5 minutes

**Note:** This attribute will be reused for all subsequent tests!

---

### Phase 1: Core Calculation Tests

**Purpose:** Test all 8 calculation setting combinations with full product lifecycle.

#### Test Configuration

- **Configs:** CALC-1 through CALC-8 (all combinations)
- **Environment:** Latest WordPress/WooCommerce, standard currency format ($1,234.56)
- **Attribute:** Use attribute created in Phase 0

#### Optimized Test Steps

| Step         | Action                              | Purpose                     | What to Verify                                           |
| ------------ | ----------------------------------- | --------------------------- | -------------------------------------------------------- |
| **4**  | Delete variable product             | Database cleanup            | Confirm deletion in `wp_posts` and related meta tables |
| **5**  | Create variable product             | Setup                       | Product created with correct post type                   |
| **6**  | Generate variations                 | WC integration              | All attribute combinations create variations             |
| **7**  | Set regular prices                  | **PRIMARY CALC TEST** | Markup correctly applied to base prices                  |
| **8**  | Increase OR decrease regular prices | Price change logic          | Markup recalculates correctly                            |
| **10** | Set sale prices                     | **SALE PRICE TEST**   | Tests Sale Price Markup setting behavior                 |
| **13** | Remove sale prices                  | Cleanup logic               | Sale prices removed, regular prices remain               |

**Steps SKIPPED (redundant):**

- Step 11-12: Increase/decrease sale prices - these test the same code path as Step 8

**Optimized Execution:** 7 steps per config = **56 base tests**

#### Special Case: Zero Price Testing

For configs with **Preserve Zero Prices = ON** (CALC-4, CALC-6, CALC-7, CALC-8):

**Additional Step:**

- Add one variation with **$0.00** base price
- Verify markup is NOT applied (price stays $0.00)

**Additional Tests:** 4 configs × 1 extra step = **4 tests**

#### Execution Count

- Standard configs: 4 × 7 = 28 tests
- Zero-price configs: 4 × 8 = 32 tests
- **Total: 60 tests**

**Time Estimate:** ~2 hours

---

### Phase 2: Display Formatting Tests

**Purpose:** Verify text formatting for all display settings independently.

#### 2A: Product-Level Display Settings

**Setup:** Use any existing product from Phase 1 tests
**Method:** Toggle setting → Reapply markup → Check output

| Setting                                  | Test Case                    | Expected Result                                  |
| ---------------------------------------- | ---------------------------- | ------------------------------------------------ |
| **Variation Description Behavior** |                              |                                                  |
|                                          | Replace + Standard Currency  | Description completely replaced with markup text |
|                                          | Replace + Euro (1.234,56 €) | Euro symbol and format display correctly         |
|                                          | Append + Standard            | Markup text appended to existing description     |
|                                          | Append + Euro                | Euro format appended correctly                   |
|                                          | Replace-NoMarkup + Standard  | Description replaced but no markup value shown   |
|                                          | Replace-NoMarkup + Euro      | Euro behavior correct (no markup value)          |
| **Include Attribute Names**        |                              |                                                  |
|                                          | ON + Reapply                 | Shows "Size: Large (+$5.00)"                     |
|                                          | OFF + Reapply                | Shows "Large (+$5.00)"                           |
| **Hide Base Price**                |                              |                                                  |
|                                          | ON + Reapply                 | Base price not visible in description            |
|                                          | OFF + Reapply                | Base price visible in description                |

**Execution Count:** 6 + 2 + 2 = **10 tests**
**Time Estimate:** 15 minutes

#### 2B: Attribute-Level Display Settings

**Setup:** Use attribute from Phase 0
**Method:** Toggle setting → Save → Check term display

| Setting                             | Test Case              | Expected Result                                        |
| ----------------------------------- | ---------------------- | ------------------------------------------------------ |
| **Add Markup to Name**        |                        |                                                        |
|                                     | ON + Standard Currency | Term name shows markup (e.g., "Large (+$5.00)")        |
|                                     | ON + Euro              | Term name shows Euro format (e.g., "Large (+5,00 €)") |
|                                     | OFF                    | Term name has no markup text                           |
| **Add Markup to Description** |                        |                                                        |
|                                     | ON + Standard          | Term description includes markup                       |
|                                     | ON + Euro              | Euro format in description                             |
|                                     | OFF                    | No markup in description                               |

**Execution Count:** 3 + 3 = **6 tests**
**Time Estimate:** 10 minutes

---

### Phase 3: Binary Flag Tests

**Purpose:** Validate the theme CSS override setting.

| Test Case                    | Configuration                   | Expected Result               |
| ---------------------------- | ------------------------------- | ----------------------------- |
| Do Not Overwrite Theme = ON  | Active theme with custom styles | Plugin preserves theme styles |
| Do Not Overwrite Theme = OFF | Active theme with custom styles | Plugin applies default styles |

**Execution Count:** **2 tests**
**Time Estimate:** 5 minutes

---

### Phase 4: High-Risk Deep Dives

#### 4A: Decimal/Rounding Stress Test 🔴 CRITICAL

**Purpose:** Validate decimal handling and rounding logic (highest-risk area per forum analysis).

**Configuration:**

- Test with: CALC-3 and CALC-5 (rounding enabled)
- Currency formats: Both standard ($1,234.56) AND Euro (1.234,56 €)
- Markup types: All 5 types from Phase 0 attribute
- Price operations: Steps 7 (set regular) and 10 (set sale)

**Specific Tests:**

- Verify rounding works correctly (e.g., $1.237 → $1.24, not $1.23)
- Verify Euro decimal separator (,) handled correctly
- Verify Euro thousand separator (.) handled correctly
- Test edge cases: $0.01, $9.99, $999.99

**Execution Count:** 2 configs × 2 currencies × 5 markup types × 2 steps = **40 tests**
**Time Estimate:** 1 hour

**IMPORTANT:** Check `debug.log` after EVERY test in this phase!

---

#### 4B: Input Validation & Security 🔴 HIGH

**Purpose:** Prevent security vulnerabilities and handle invalid input gracefully.

**Configuration:** CALC-1 (baseline)
**Target Fields:**

- Attribute term names
- Attribute term descriptions
- Markup value fields

**Test Inputs:**

| Input Type             | Test Data                         | Expected Behavior                            |
| ---------------------- | --------------------------------- | -------------------------------------------- |
| **Null/Empty**   | Blank field                       | Graceful handling, no fatal errors           |
| **Invalid Data** | "abc" in numeric field            | Validation error, value rejected             |
| **XSS Attack**   | `<script>alert('xss')</script>` | Input sanitized/escaped, script not executed |
| **Valid Data**   | Proper format values              | Accepted and stored correctly                |

**Execution Count:** 3 fields × 4 input types = **12 tests**
**Time Estimate:** 20 minutes

**CRITICAL:** Test XSS in both admin AND frontend display!

---

#### 4C: Version Compatibility 🟢 MAJOR RELEASES ONLY

**Purpose:** Ensure compatibility with recent WordPress/WooCommerce versions.

**Target Versions:**

- WordPress: N-1, N-2 (two previous major versions)
- WooCommerce: N-1, N-2

**Calculation Testing:**

- Configuration: CALC-8 (all features enabled)
- Steps: 4-7, 10, 13 (optimized product lifecycle)
- Execute on each version combination

**Display Testing:**

- Configuration: Replace + Include Names ON + Hide Base ON
- Steps: Just reapply markup and verify output
- Execute on each version

**Execution Count:**

- Calculation: 2 versions × 6 steps = 12 tests
- Display: 2 versions × 1 step = 2 tests
- **Total: 14 tests**

**Time Estimate:** 30 minutes

---

#### 4D: Database Integrity 🔴 HIGH

**Purpose:** Ensure no orphaned data, proper cascading deletes, and clean database operations.

**Test Operations:**

| Step         | Operation                                 | Verification                                                           |
| ------------ | ----------------------------------------- | ---------------------------------------------------------------------- |
| **1**  | Delete attribute with terms               | Check `wp_terms`, `wp_term_taxonomy`, `wp_termmeta` - no orphans |
| **4**  | Delete variable product                   | Check `wp_posts`, `wp_postmeta` - parent and variations deleted    |
| **14** | Change markup on terms                    | Verify term meta updated correctly                                     |
| **15** | Reapply markup (product bulk action)      | Verify prices updated in `wp_postmeta`                               |
| **16** | Reprice (individual product)              | Verify single product prices updated                                   |
| **17** | Reapply markup (bulk action all products) | Verify all applicable products updated                                 |

**Execution Count:** 6 operations × 2 (execute + verify) = **12 tests**
**Time Estimate:** 30 minutes

**Tools:** Use phpMyAdmin or Adminer to verify database state

---

## Test Execution Summary

### Standard Regression Suite (Every Release)

| Phase              | Description                   | Test Count          | Time Estimate        |
| ------------------ | ----------------------------- | ------------------- | -------------------- |
| **Phase 0**  | Attribute Setup               | 3                   | 5 min                |
| **Phase 1**  | Calculation Tests (8 configs) | 60                  | 2 hours              |
| **Phase 2**  | Display Formatting            | 16                  | 25 min               |
| **Phase 3**  | Binary Flags                  | 2                   | 5 min                |
| **Phase 4A** | Decimal/Rounding              | 40                  | 1 hour               |
| **Phase 4B** | Input Validation              | 12                  | 20 min               |
| **Phase 4D** | Database Integrity            | 12                  | 30 min               |
| **TOTAL**    |                               | **145 tests** | **~4.5 hours** |

### Major Release Suite (Add to Regression)

| Phase              | Description           | Test Count          | Time Estimate      |
| ------------------ | --------------------- | ------------------- | ------------------ |
| **Phase 4C** | Version Compatibility | 14                  | 30 min             |
| **TOTAL**    |                       | **159 tests** | **~5 hours** |

---

## Detailed Test Steps Reference

This section provides detailed instructions for each test step referenced in the phases above.

### Section 0: Plugin Settings

#### Step 0: Test Option Drop-down Behavior

**Setting Location:** Markup by Attribute → Settings → General

**Test Cases:**

- **0a:** Set to "Do NOT show markup in options drop-down"
  - **Verify:** Variation selector shows only attribute value (e.g., "Large")
- **0b:** Set to "Show markup WITH currency symbol"
  - **Verify:** Variation selector shows "Large (+$5.00)" with currency
- **0c:** Set to "Show markup WITHOUT currency symbol"
  - **Verify:** Variation selector shows "Large (+5.00)" without currency

**Note:** This is independent of other settings and can be tested separately.

---

### Section 1: Attribute Management

#### Step 1: Delete Attribute with Terms

**Procedure:**

1. Navigate to Products → Attributes
2. Select existing test attribute (if any)
3. Click "Delete"
4. Confirm deletion

**Database Verification:**

```sql
-- Check wp_terms (should be empty for deleted attribute)
SELECT * FROM wp_terms WHERE name LIKE 'Test%';

-- Check wp_term_taxonomy
SELECT * FROM wp_term_taxonomy WHERE taxonomy = 'pa_test-size';

-- Check wp_termmeta (should have no orphaned meta)
SELECT * FROM wp_termmeta WHERE term_id NOT IN (SELECT term_id FROM wp_terms);
```

**Expected Result:** All attribute data removed cleanly, no orphaned records

---

#### Step 2: Create Attribute

**Procedure:**

1. Navigate to Products → Attributes
2. Click "Add Attribute"
3. Enter:
   - **Name:** Balderdash
   - **Slug:** balderdash
   - **Enable Archives:** (optional)
4. Click "Add Attribute"

**Verification:** Attribute appears in attributes list

---

#### Step 3: Create Terms with Markup

**Procedure:** For each term below:

1. Click "Configure terms" for Balderdash attribute
2. Add new term with specified markup
3. Include description for some terms, leave blank for others

**Terms to Create:**

| Term                          | Markup Type         | Markup Value | Description                  |
| ----------------------------- | ------------------- | ------------ | ---------------------------- |
| **3a: Zoom**            | Positive Percentage | `20%`      | "Small size with 20% markup" |
| **3b: Pantless**        | Positive Fixed      | `$10.00`   | (leave blank)                |
| **3c: Flibbertigibbet** | Negative Percentage | `-15%`     | "Large size with discount"   |
| **3d: Widdershins**     | Negative Fixed      | `-$5.00`   | (leave blank)                |
| **3e: Cattywampus**     | No Markup           | (blank)      | "Custom size, no markup"     |

**Verification:**

- All attributes appear in the attribute list
- All terms appear in the term list
- Markups appear in the term list
- Term list is sortable by all columns
- Markup values saved correctly
- Descriptions saved where provided

---

### Section 2: Product Management

#### Step 4: Delete Variable Product

**Procedure:**

1. Navigate to Products → All Products
2. Find existing test product (if any)
3. Move to Trash or Delete Permanently
4. Confirm deletion

**Database Verification:**

```sql
-- Check product and variations deleted
SELECT * FROM wp_posts WHERE post_title LIKE '%Test Product%';

-- Check for orphaned meta
SELECT * FROM wp_postmeta WHERE post_id NOT IN (SELECT ID FROM wp_posts);
```

**Expected Result:** Product, all variations, and meta data removed cleanly

---

#### Step 5: Create Variable Product

**Procedure:**

1. Navigate to Products → Add New
2. Enter product name: "Test Variable Product"
3. Set product type: Variable Product
4. **5a: Add Global Attributes with Markups**
   - Go to Product Data → Attributes
   - Add "Balderdash" (created in Step 2)
   - Check "Used for variations"
   - Select all terms
   - Click "Save attributes"
5. **5b: Add Local Attribute (No Markups)**
   - Add new custom attribute "Color"
   - Add values: "Red, Blue, Green"
   - Check "Used for variations"
   - Click "Save attributes"

**Verification:**

- Both attributes appear in product
- Global attribute shows markup values
- Local attribute has no markup capability

---

#### Step 6: Generate Variations

**Procedure:**

1. Go to Product Data → Variations
2. Select "Create variations from all attributes"
3. Click "Go"
4. Confirm generation

**Verification:**

- All combinations created (5 sizes × 3 colors = 15 variations)
- Each variation shows attribute combination
- Variations have no prices yet

---

#### Step 7: Set Regular Prices

**This is a PRIMARY TEST for markup calculation!**

**Procedure:**

1. Go to Variations tab
2. For each variation, set Regular Price:
   - Small variations: $100.00
   - Medium variations: $200.00
   - Large variations: $150.00
   - X-Large variations: $175.00
   - Custom variations: $125.00

**Verification - Calculated Prices:**

**With Markup Applied:**

| Term    | Base Price                 | Markup  | Expected Final Price |
| ------- | -------------------------- | ------- | -------------------- |
| Small   | $100.00 | +20% | $120.00   |         |                      |
| Medium  | $200.00 | +$10             | $210.00 |                      |
| Large   | $150.00 | -15% | $127.50   |         |                      |
| X-Large | $175.00 | -$5              | $170.00 |                      |
| Custom  | $125.00 | (none) | $125.00 |         |                      |

**Additional Checks:**

- **7a: With product descriptions** - Check variation descriptions contain markup text
- **7b: Without product descriptions** - Verify markup still calculates correctly

**Database Verification:**

```sql
-- Check variation prices in meta
SELECT p.post_title, pm.meta_key, pm.meta_value
FROM wp_posts p
JOIN wp_postmeta pm ON p.ID = pm.post_id
WHERE p.post_type = 'product_variation'
AND pm.meta_key IN ('_regular_price', '_price')
ORDER BY p.post_title;
```

---

#### Step 8: Increase Regular Prices

**Purpose:** Verify markup recalculates when prices change

**Procedure:**

1. Select any 2-3 variations
2. Increase regular price by $50.00
3. Save

**Verification:**

- Markup recalculates based on NEW base price
- Final prices correct

**Example:**

- Small (was $100 base → $120 final)
- Change to $150 base
- Should become $180 final ($150 + 20%)

---

#### Step 9: Decrease Regular Prices

**Purpose:** Verify markup recalculates when prices decrease

**Procedure:**

1. Select the same variations from Step 8
2. Decrease regular price by $25.00
3. Save

**Verification:**

- Markup recalculates correctly
- Final prices correct

---

#### Step 10: Set Sale Prices

**This tests the Sale Price Markup setting!**

**Procedure:**

1. For 3-4 variations, add Sale Price lower than Regular Price
2. Set Sale Prices:
   - Small: $90.00 (regular $100)
   - Medium: $180.00 (regular $200)
   - Large: $130.00 (regular $150)

**Verification - Depends on Config:**

**If Sale Price Markup = ON:**

- Markup APPLIES to sale price
- Small: $90 + 20% = $108.00 final
- Medium: $180 + $10 = $190.00 final
- Large: $130 - 15% = $110.50 final

**If Sale Price Markup = OFF:**

- Sale price used as-is (no markup)
- Small: $90.00 final
- Medium: $180.00 final
- Large: $130.00 final

**Database Verification:**

```sql
SELECT p.post_title, pm.meta_key, pm.meta_value
FROM wp_posts p
JOIN wp_postmeta pm ON p.ID = pm.post_id
WHERE p.post_type = 'product_variation'
AND pm.meta_key IN ('_regular_price', '_sale_price', '_price')
ORDER BY p.post_title, pm.meta_key;
```

---

#### Step 11: Increase Sale Prices (SKIP - Redundant)

**Note:** This tests the same code path as Step 8. Only execute if specifically troubleshooting sale price issues.

---

#### Step 12: Decrease Sale Prices (SKIP - Redundant)

**Note:** This tests the same code path as Step 9. Only execute if specifically troubleshooting sale price issues.

---

#### Step 13: Remove Sale Prices

**Purpose:** Verify sale price removal and reversion to regular pricing

**Procedure:**

1. For variations with sale prices, clear the Sale Price field
2. Save

**Verification:**

- Sale price removed from database
- Display price reverts to regular price (with markup if applicable)
- Regular prices unchanged

---

### Section 3: Repricing Operations

#### Step 14: Change Markup on Terms

**Purpose:** Setup for testing repricing functions

**Procedure:**

1. Navigate to Products → Attributes → Balderdash → Terms
2. Edit term markups:
   - Zoom: Change from 20% to 25%
   - Pantless: Change from $10 to $15
   - Flibbertigibbet: Change from -15% to -10%

**Verification:**

- New markup values saved
- **IMPORTANT:** Existing product prices NOT automatically updated (this is expected!)

---

#### Step 15: Reapply Markup to Prices (Product Bulk Action)

**Purpose:** Test bulk repricing from product edit screen

**Procedure:**

1. Edit the Test Variable Product
2. Go to Variations tab
3. Select "Reapply markup to prices" from Bulk Actions dropdown
4. Select all variations
5. Click "Go"

**Verification:**

- All variation prices recalculated with NEW markup values from Step 14
- Prices match expected calculations
- Check `debug.log` for any errors

---

#### Step 16: Reprice (Individual Product Action)

**Purpose:** Test single-product repricing

**Procedure:**

1. Navigate to Products → All Products
2. Hover over Test Variable Product
3. Click "Reprice" quick action (if available) OR
4. Use individual product reprice function

**Verification:**

- Product variations repriced
- Matches results from Step 15

---

#### Step 17: Reapply Markups (Bulk Action All Products)

**Purpose:** Test bulk repricing across multiple products

**Setup:** Create 2-3 additional variable products with markup attributes

**Procedure:**

1. Navigate to Products → All Products
2. Select multiple products (including Test Variable Product)
3. Select "Reapply Markups" from Bulk Actions
4. Click "Apply"

**Verification:**

- All selected products repriced
- Variations across all products updated
- Database shows correct prices for all products
- Check `debug.log` for errors

**Database Verification:**

```sql
-- Check that all variations across products were updated
SELECT p.post_title, p.post_modified, pm.meta_key, pm.meta_value
FROM wp_posts p
JOIN wp_postmeta pm ON p.ID = pm.post_id
WHERE p.post_type = 'product_variation'
AND pm.meta_key = '_price'
ORDER BY p.post_modified DESC;
```

---

## Test Execution Checklist

### Pre-Test Setup

- [ ] Fresh WordPress installation OR staging site
- [ ] Latest WooCommerce installed and activated
- [ ] Markup by Attribute plugin installed and activated
- [ ] WP_DEBUG enabled in `wp-config.php`
- [ ] Database access available (phpMyAdmin/Adminer)
- [ ] Browser developer tools available
- [ ] Test data documented (prices, expected results)

### During Testing - ALWAYS Check

- [ ] **`debug.log`** after each operation (critical!)
- [ ] Browser console for JavaScript errors
- [ ] Network tab for AJAX errors
- [ ] Database state after critical operations
- [ ] Frontend display matches admin values

### Input Validation Checklist

When testing ANY field that accepts user input:

- [ ] **Null/Empty:** Leave field blank, attempt to save
- [ ] **Invalid Data:** Enter wrong data type (text in number field, etc.)
- [ ] **XSS Test:** Enter `<script>alert('xss')</script>`
- [ ] **Valid Data:** Enter correct format data

### Currency Notation Testing

For display formatting tests, repeat with:

- [ ] **Standard:** $1,234.56 (period decimal, comma thousands)
- [ ] **Euro:** 1.234,56 € (comma decimal, period thousands)

### Version Compatibility Testing (Major Releases)

- [ ] Current WordPress + Current WooCommerce (baseline)
- [ ] WP N-1 + WC N-1
- [ ] WP N-2 + WC N-2

---

## Test Execution Tracking Template

### Phase 1 Tracking Sheet

| Config | Sale Markup | Round | Zero | Steps 4-7 | Step 8 | Step 10 | Step 13 | Zero Test | Status | Notes |
| ------ | ----------- | ----- | ---- | --------- | ------ | ------- | ------- | --------- | ------ | ----- |
| CALC-1 | OFF         | OFF   | OFF  | [ ]       | [ ]    | [ ]     | [ ]     | N/A       |        |       |
| CALC-2 | ON          | OFF   | OFF  | [ ]       | [ ]    | [ ]     | [ ]     | N/A       |        |       |
| CALC-3 | OFF         | ON    | OFF  | [ ]       | [ ]    | [ ]     | [ ]     | N/A       |        |       |
| CALC-4 | OFF         | OFF   | ON   | [ ]       | [ ]    | [ ]     | [ ]     | [ ]       |        |       |
| CALC-5 | ON          | ON    | OFF  | [ ]       | [ ]    | [ ]     | [ ]     | N/A       |        |       |
| CALC-6 | ON          | OFF   | ON   | [ ]       | [ ]    | [ ]     | [ ]     | [ ]       |        |       |
| CALC-7 | OFF         | ON    | ON   | [ ]       | [ ]    | [ ]     | [ ]     | [ ]       |        |       |
| CALC-8 | ON          | ON    | ON   | [ ]       | [ ]    | [ ]     | [ ]     | [ ]       |        |       |

### Phase 2 Tracking Sheet

**Product Display:**

| Setting       | Test Case        | Standard | Euro | Status | Notes |
| ------------- | ---------------- | -------- | ---- | ------ | ----- |
| Var Desc      | Replace          | [ ]      | [ ]  |        |       |
| Var Desc      | Append           | [ ]      | [ ]  |        |       |
| Var Desc      | Replace-NoMarkup | [ ]      | [ ]  |        |       |
| Include Names | ON               | [ ]      | N/A  |        |       |
| Include Names | OFF              | [ ]      | N/A  |        |       |
| Hide Base     | ON               | [ ]      | N/A  |        |       |
| Hide Base     | OFF              | [ ]      | N/A  |        |       |

**Attribute Display:**

| Setting     | Test Case | Standard | Euro | Status | Notes |
| ----------- | --------- | -------- | ---- | ------ | ----- |
| Add to Name | ON        | [ ]      | [ ]  |        |       |
| Add to Name | OFF       | [ ]      | N/A  |        |       |
| Add to Desc | ON        | [ ]      | [ ]  |        |       |
| Add to Desc | OFF       | [ ]      | N/A  |        |       |

### Phase 4A Tracking Sheet (Decimal/Rounding)

| Config                  | Currency | Markup Type    | Step 7 | Step 10 | Calculations Correct | Status | Notes |
| ----------------------- | -------- | -------------- | ------ | ------- | -------------------- | ------ | ----- |
| CALC-3                  | Standard | Positive %     | [ ]    | [ ]     | [ ]                  |        |       |
| CALC-3                  | Standard | Positive Fixed | [ ]    | [ ]     | [ ]                  |        |       |
| CALC-3                  | Standard | Negative %     | [ ]    | [ ]     | [ ]                  |        |       |
| CALC-3                  | Standard | Negative Fixed | [ ]    | [ ]     | [ ]                  |        |       |
| CALC-3                  | Standard | No Markup      | [ ]    | [ ]     | [ ]                  |        |       |
| CALC-3                  | Euro     | Positive %     | [ ]    | [ ]     | [ ]                  |        |       |
| CALC-3                  | Euro     | Positive Fixed | [ ]    | [ ]     | [ ]                  |        |       |
| CALC-3                  | Euro     | Negative %     | [ ]    | [ ]     | [ ]                  |        |       |
| CALC-3                  | Euro     | Negative Fixed | [ ]    | [ ]     | [ ]                  |        |       |
| CALC-3                  | Euro     | No Markup      | [ ]    | [ ]     | [ ]                  |        |       |
| *(Repeat for CALC-5)* |          |                |        |         |                      |        |       |

---

## Appendix: Risk Analysis

### Why This Approach Works

**Research Foundation:**

- Pairwise (2-way) testing catches 70-90% of bugs (per NIST study)
- Most software bugs involve interactions of 2 variables, not 3+
- Testing all combinations has diminishing returns vs. pairwise

**Domain-Specific Intelligence:**

- Support forum analysis identified actual bug patterns
- Understanding setting independence eliminates false dependencies
- Redundant test steps identified through code analysis

### What We're NOT Testing (And Why That's OK)

**Not Testing:**

- Every possible price value (testing representative values)
- Every theme (testing theme override mechanism)
- Every possible product configuration (testing variable products with global attributes)
- Older than N-2 WordPress/WooCommerce versions (market share too small)

**Why It's Acceptable:**

- These are infinite test spaces - must use representative sampling
- Risk vs. coverage tradeoff optimized for 4.5 hour test execution
- Calculation logic is deterministic (if it works for $100, works for $538.27)

### High-Risk Areas Requiring Extra Attention

1. **Decimal/Rounding** (Phase 4A)

   - Forum shows this is #1 bug source
   - Currency notation differences create edge cases
   - Floating point arithmetic issues possible
2. **Sale Price Calculation** (CALC-2, CALC-5, CALC-6, CALC-8)

   - Sale Price Markup ON/OFF creates different code paths
   - Interaction with rounding can cause issues
3. **Database Integrity** (Phase 4D)

   - Bulk operations can create orphaned data
   - Cascading deletes must work correctly
   - Version upgrades can break schema

### Medium-Risk Areas

1. **Display Formatting**

   - Less critical (cosmetic issues)
   - But XSS vulnerabilities possible
   - Currency symbol placement can break layouts
2. **Zero Price Handling**

   - Edge case, but important for "free" products
   - Interaction with markup calculation needs verification

### Low-Risk Areas

1. **Theme CSS Override**

   - Simple boolean, well-isolated code
   - Visual issue only (no data corruption)
2. **Repricing Operations**

   - Forum shows most "bugs" were user error
   - Documentation issue, not code issue

---

## Revision History

| Version | Date       | Changes                         | Author     |
| ------- | ---------- | ------------------------------- | ---------- |
| 1.0     | 2025-01-29 | Initial test plan created       | Mark T.    |
| 1.1     | 2025-01-29 | Test plan cleanup and expansion | Akina (AI) |

---

**End of Test Plan**

*For questions or issues with this test plan, contact the development team.*
