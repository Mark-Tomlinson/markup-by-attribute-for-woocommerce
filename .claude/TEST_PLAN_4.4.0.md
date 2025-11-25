# Test Plan for Version 4.4.0
## Markup by Attribute for WooCommerce

**Version:** 4.4.0
**Test Environment:** PHP 7.4.3, WordPress 5.0.8, WooCommerce 4.0.0
**Test Server:** 192.168.1.191/backrev/
**Total Tests:** 32

---

## Test Categories

1. **Security Tests** (6 tests) - Issues #1-6
2. **Performance Tests** (1 test) - Issue #7
3. **Code Quality Tests** (6 tests) - Issues #8-13
4. **Core Functionality Tests** (10 tests)
5. **European Decimal Tests** (3 tests)
6. **Reapply Markup Tests** (3 tests)
7. **Edge Case Tests** (3 tests)

---

## 1. Security Tests (Issues #1-6)

### Test 1.1: Input Validation
**Issue:** #1 - Input validation enhancement
**Steps:**
1. Navigate to Products → Attributes → Edit attribute term
2. Enter malicious markup values: `<script>alert('xss')</script>`, `'; DROP TABLE--`, `../../etc/passwd`
3. Save term
4. Verify all malicious input is properly sanitized

**Expected:** Input is sanitized, no XSS/SQL injection occurs
**Status:** [ ]

### Test 1.2: Nonce Verification - AJAX Handlers
**Issue:** #2 - Nonce verification for AJAX
**Steps:**
1. Open browser developer tools → Network tab
2. Trigger AJAX action (e.g., reapply markups)
3. Capture request, modify/remove nonce parameter
4. Replay modified request

**Expected:** Request fails with nonce error
**Status:** [ ]

### Test 1.3: Capability Checks
**Issue:** #3 - Administrative capability checks
**Steps:**
1. Create user with 'Shop Manager' role
2. Log in as Shop Manager
3. Attempt to access attribute markup settings
4. Attempt to modify product markups
5. Attempt bulk price actions

**Expected:** All actions work for authorized roles, fail for unauthorized
**Status:** [ ]

### Test 1.4: XSS Protection in Output
**Issue:** #4 - XSS protection in markup display
**Steps:**
1. Create attribute term with markup containing HTML: `5% <b>bonus</b>`
2. View product variation dropdown on frontend
3. Check variation description
4. Inspect HTML source

**Expected:** HTML is escaped, displays as text not rendered HTML
**Status:** [ ]

### Test 1.5: SQL Injection Prevention
**Issue:** #5 - SQL injection in database queries
**Steps:**
1. Attempt SQL injection in markup field: `5'; DROP TABLE wp_terms--`
2. Use bulk price actions with SQL injection attempts
3. Verify database integrity

**Expected:** SQL is escaped, no database changes occur
**Status:** [ ]

### Test 1.6: CSRF Protection - Bulk Actions
**Issue:** #6 - CSRF protection for bulk actions
**Steps:**
1. Navigate to Products → All Products
2. Select variations
3. Choose 'Set regular price' bulk action
4. Capture form submission
5. Replay without valid nonce

**Expected:** Bulk action fails without valid nonce
**Status:** [ ]

---

## 2. Performance Tests (Issue #7)

### Test 2.1: Database Query Optimization
**Issue:** #7 - Database query performance
**Steps:**
1. Enable Query Monitor plugin
2. Create product with 20+ variations
3. Load product edit page
4. Check number of database queries for markup retrieval
5. Apply bulk price action
6. Monitor query performance

**Expected:** Reduced redundant queries, efficient caching
**Status:** [ ]

---

## 3. Code Quality Tests (Issues #8-13)

### Test 3.1: Return Type Declarations - options.php
**Issue:** #8-13 - PHP 7.4 return types
**File:** src/frontend/options.php (2 methods)
**Steps:**
1. Enable PHP error reporting (E_ALL)
2. Load product page on frontend
3. Select variation with markup
4. Verify no type errors

**Expected:** No PHP errors, proper type handling
**Status:** [ ]

### Test 3.2: Return Type Declarations - product.php
**File:** src/backend/product.php (8 methods)
**Steps:**
1. Edit product in admin
2. Use reapply markups functionality
3. Refresh product general panel
4. Handle bulk price actions
5. Verify no type errors in console/logs

**Expected:** All methods execute without type errors
**Status:** [ ]

### Test 3.3: Return Type Declarations - Handler Files
**Files:**
- markupdeletehandler.php (1 method)
- priceupdatehandler.php (2 methods)
- pricesethandler.php (9 methods)

**Steps:**
1. Delete markup from variation
2. Update prices with markups
3. Set new regular/sale prices
4. Verify all handlers work correctly

**Expected:** All handlers execute without type errors
**Status:** [ ]

### Test 3.4: Return Type Declarations - settings.php
**File:** src/backend/settings.php (1 method)
**Steps:**
1. Navigate to WooCommerce → Settings → Markup by Attribute
2. Modify settings
3. Save settings
4. Verify no type errors

**Expected:** Settings save correctly without errors
**Status:** [ ]

### Test 3.5: Return Type Declarations - term.php
**File:** src/backend/term.php (5 methods)
**Steps:**
1. Add new attribute term with markup
2. Edit existing term
3. Delete term
4. View term list
5. Verify no type errors

**Expected:** All term operations work without type errors
**Status:** [ ]

### Test 3.6: PHP 7.4 Compatibility Verification
**Overall Return Type Test**
**Steps:**
1. Run plugin on PHP 7.4.3 environment
2. Execute all 31 functions with return types
3. Monitor PHP error log
4. Verify no constructor/magic method type issues

**Expected:** Zero PHP errors, full PHP 7.4 compatibility
**Status:** [ ]

---

## 4. Core Functionality Tests

### Test 4.1: Complete Attribute Creation Workflow
**Steps:**
1. Navigate to Products → Attributes
2. Create new global attribute: "Test Size"
3. Add terms: "Small" (-10%), "Medium" (0), "Large" (+5.00), "X-Large" (+15%)
4. Create variable product
5. Add "Test Size" attribute
6. Generate all variations
7. Verify markup field appears on each variation

**Expected:** Attribute and terms created successfully with markups
**Status:** [ ]

### Test 4.2: Fixed Value Markup
**Steps:**
1. Create attribute term with fixed markup: +5.00
2. Set base price: 20.00
3. Verify variation price: 25.00

**Expected:** Price = 25.00 (20 + 5)
**Status:** [ ]

### Test 4.3: Percentage Markup
**Steps:**
1. Create attribute term with percentage markup: 10%
2. Set base price: 50.00
3. Verify variation price: 55.00

**Expected:** Price = 55.00 (50 + 10%)
**Status:** [ ]

### Test 4.4: Negative Markdown
**Steps:**
1. Create attribute term with negative markup: -7.50
2. Set base price: 30.00
3. Verify variation price: 22.50

**Expected:** Price = 22.50 (30 - 7.50)
**Status:** [ ]

### Test 4.5: Multiple Attribute Markups
**Steps:**
1. Create product with two markup attributes:
   - Color: Blue (+3.00)
   - Size: Large (+5.00)
2. Set base price: 20.00
3. Verify variation "Blue / Large" price: 28.00

**Expected:** Price = 28.00 (20 + 3 + 5)
**Status:** [ ]

### Test 4.6: Sale Price with Markup
**Steps:**
1. Create variation with markup: +5.00
2. Set regular price: 100.00 (actual: 105.00)
3. Set sale price: 80.00 (actual: 85.00)
4. Verify both prices have markup applied

**Expected:** Regular: 105.00, Sale: 85.00
**Status:** [ ]

### Test 4.7: Bulk Set Regular Price
**Steps:**
1. Select multiple variations
2. Use WooCommerce bulk action: "Set regular price"
3. Enter: 25.00
4. Verify each variation has markup added to 25.00

**Expected:** Variations show 25.00 + respective markups
**Status:** [ ]

### Test 4.8: Bulk Set Sale Price
**Steps:**
1. Select multiple variations with different markups
2. Use bulk action: "Set sale price"
3. Enter: 20.00
4. Verify markup applies to sale price

**Expected:** Each variation: 20.00 + respective markups
**Status:** [ ]

### Test 4.9: Variation Description Display
**Steps:**
1. Create variation with markup
2. Set price
3. View variation description on frontend
4. Verify itemized price breakdown is shown

**Expected:** Description shows: "Base Price: X, Markup: Y, Total: Z"
**Status:** [ ]

### Test 4.10: Frontend Dropdown Display
**Steps:**
1. Create product with markup attributes
2. View product page
3. Check variation dropdown options
4. Verify price differences shown (+$5.00, etc.)

**Expected:** Dropdown shows markup amounts for customer clarity
**Status:** [ ]

---

## 5. European Decimal Tests

### Test 5.1: Comma Decimal Input
**European Decimal Handling Verification**
**Steps:**
1. Change WordPress locale to German (de_DE)
2. Create attribute term with comma markup: "5,50"
3. Save term
4. Verify markup is stored correctly as 5.50

**Expected:** Comma decimal accepted and normalized
**Status:** [ ]

### Test 5.2: Percentage with Comma
**Steps:**
1. Create term with comma percentage: "7,5%"
2. Set base price: 100,00
3. Verify variation price: 107,50

**Expected:** Comma notation handled in percentages
**Status:** [ ]

### Test 5.3: wc_format_decimal() → is_numeric() Order
**Code Quality Verification**
**Steps:**
1. Review code: src/backend/handlers/pricesethandler.php
2. Verify all 5 `is_numeric()` calls occur AFTER `wc_format_decimal()`
3. Test with comma input to ensure proper normalization

**Expected:** All numeric checks happen after normalization
**Status:** [ ]

---

## 6. Reapply Markup Tests (3 Methods)

### Test 6.1: Reapply Method 1 - Individual Product
**Product Edit Page → Reapply Button**
**Steps:**
1. Edit product with variations
2. Change attribute term markup
3. Click "Reapply Markups" button on product edit page
4. Verify all variation prices updated

**Expected:** Prices recalculated with new markup
**Status:** [ ]

### Test 6.2: Reapply Method 2 - Bulk Action
**Products List → Bulk Action**
**Steps:**
1. Navigate to Products → All Products
2. Select multiple products
3. Choose "Reapply Markups" bulk action
4. Apply
5. Verify all selected products updated

**Expected:** Bulk reapply works for multiple products
**Status:** [ ]

### Test 6.3: Reapply Method 3 - Settings Page
**WooCommerce Settings → Reapply All**
**Steps:**
1. Navigate to WooCommerce → Settings → Markup by Attribute
2. Click "Reapply All Markups" button
3. Wait for completion
4. Verify ALL products in store updated

**Expected:** Global reapply updates entire catalog
**Status:** [ ]

---

## 7. Edge Case Tests

### Test 7.1: Zero Markup
**Steps:**
1. Create term with markup: 0
2. Set base price: 50.00
3. Verify price remains: 50.00

**Expected:** No change to price
**Status:** [ ]

### Test 7.2: Very Large Markup
**Steps:**
1. Create term with markup: 999.99
2. Set base price: 10.00
3. Verify price: 1009.99

**Expected:** Large markup handled correctly
**Status:** [ ]

### Test 7.3: Very Small Percentage
**Steps:**
1. Create term with markup: 0.1%
2. Set base price: 100.00
3. Verify price: 100.10

**Expected:** Small percentage calculated accurately
**Status:** [ ]

---

## Test Summary

**Total Tests:** 32
- Security: 6 tests
- Performance: 1 test
- Code Quality: 6 tests
- Core Functionality: 10 tests
- European Decimals: 3 tests
- Reapply Methods: 3 tests
- Edge Cases: 3 tests

**Testing Schedule:**
- **Start:** Monday, November 23, 2025
- **Completion Target:** Tuesday, November 24, 2025
- **Release:** Wednesday, November 25, 2025

**Testing Environments:**
- Primary: PHP 7.4.3 (192.168.1.191/backrev/)
- Secondary: PHP 8+ (192.168.1.90/wpdev/)

---

*Created: November 20, 2025*
*By: Akina 🌸*
*Status: Ready for execution*
