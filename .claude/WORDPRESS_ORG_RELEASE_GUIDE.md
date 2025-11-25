# WordPress.org Release Guide
## Markup by Attribute for WooCommerce

Step-by-step guide for releasing new versions to WordPress.org using TortoiseSVN.

---

## Pre-Release Checklist

### 1. Complete All Testing
- [ ] All tests in TEST_PLAN_4.4.0.md passed
- [ ] PHP 7.4 compatibility verified
- [ ] PHP 8+ compatibility verified
- [ ] No errors in debug logs
- [ ] European decimal handling working
- [ ] All new features functional

### 2. Update Version Numbers
Update version in these files:

#### markup-by-attribute-for-woocommerce.php
```php
/**
 * Plugin Name: Markup by Attribute for WooCommerce
 * Version: 4.4.0
 * ...
 */

define('MT2MBA_VERSION', '4.4.0');
```

#### readme.txt
```
Version: 4.4.0
Stable tag: 4.4.0
```

Add changelog entry at top of `== Changelog ==` section.

### 3. Final Git Commit
```bash
cd /home/mark/markup-by-attribute-for-woocommerce
git add .
git commit -m "Release version 4.4.0"
git push origin Security-Enhancements
```

### 4. Merge to Master
```bash
git checkout master
git merge Security-Enhancements
git push origin master
```

### 5. Create Git Tag
```bash
git tag v4.4.0
git push origin v4.4.0
```

---

## WordPress.org SVN Release Process

### SVN Repository Location
**Local Path:** `C:\Users\markt\My Drive\MT2_Tech\SVN\markup-by-attribute-for-woocommerce`

### Step 1: Update SVN Trunk

1. **Navigate to SVN directory**
   ```
   C:\Users\markt\My Drive\MT2_Tech\SVN\markup-by-attribute-for-woocommerce
   ```

2. **Update from WordPress.org repository**
   - Right-click in directory → TortoiseSVN → SVN Update
   - This pulls latest from WordPress.org

3. **Copy files from Git to SVN trunk**
   - Copy ALL files from Git repository to SVN `trunk/` directory
   - Exclude these directories/files:
     - `.git/`
     - `.github/`
     - `.gitignore`
     - `.claude/` (development docs)
     - `.wiki/` (GitHub wiki)
     - `node_modules/` (if exists)
     - `tests/` (if exists)
     - Any other development-only files

4. **Review changes in TortoiseSVN**
   - Right-click in trunk/ → TortoiseSVN → Check for Modifications
   - Review all changes:
     - Green plus (+) = New files to add
     - Red minus (-) = Deleted files to remove
     - Blue pencil = Modified files
     - Question mark (?) = Unversioned files

5. **Add new files**
   - Right-click unversioned files → TortoiseSVN → Add
   - Verify you're not adding development files

6. **Delete removed files**
   - Right-click missing files → TortoiseSVN → Delete
   - Confirms removal from repository

7. **Commit trunk changes**
   - Right-click trunk/ → TortoiseSVN → SVN Commit
   - Commit message: "Update trunk for version 4.4.0 release"
   - Click OK to commit

### Step 2: Create Tags for New Version

1. **Navigate to SVN root**
   ```
   C:\Users\markt\My Drive\MT2_Tech\SVN\markup-by-attribute-for-woocommerce
   ```

2. **Use TortoiseSVN Branch/Tag**
   - Right-click in directory → TortoiseSVN → Branch/Tag
   - **From WC at URL:**
     ```
     https://plugins.svn.wordpress.org/markup-by-attribute-for-woocommerce/trunk
     ```
   - **To URL:**
     ```
     https://plugins.svn.wordpress.org/markup-by-attribute-for-woocommerce/tags/4.4.0
     ```
   - **Log message:** "Tagging version 4.4.0"
   - **Head revision:** Selected
   - Click OK

3. **Verify tag creation**
   - Browse to: https://plugins.svn.wordpress.org/markup-by-attribute-for-woocommerce/tags/
   - Confirm 4.4.0 directory exists

### Step 3: Update Stable Tag

The `Stable tag:` in readme.txt controls which version users download.

1. **Verify readme.txt in trunk has:**
   ```
   Stable tag: 4.4.0
   ```

2. **If not updated, edit and commit:**
   - Edit `trunk/readme.txt`
   - Change `Stable tag: 4.4.0`
   - Right-click trunk/ → TortoiseSVN → SVN Commit
   - Commit message: "Update stable tag to 4.4.0"

---

## Post-Release Verification

### 1. WordPress.org Plugin Page (15-30 minutes after release)
Visit: https://wordpress.org/plugins/markup-by-attribute-for-woocommerce/

Verify:
- [ ] Version shows 4.4.0
- [ ] Last updated date is today
- [ ] Download button works
- [ ] Changelog displays correctly
- [ ] Screenshots still show

### 2. Test Installation from WordPress.org
1. Create fresh WordPress install (or use test site)
2. Search for "Markup by Attribute" in Plugins → Add New
3. Install plugin
4. Verify version 4.4.0 installed
5. Activate and test basic functionality

### 3. Test Plugin Update
1. On site with older version installed
2. Check for updates
3. Verify 4.4.0 shows as available update
4. Update plugin
5. Verify update successful

### 4. Monitor Support Forums
- Check: https://wordpress.org/support/plugin/markup-by-attribute-for-woocommerce/
- Watch for any issues reported by users
- Respond to questions promptly

---

## Troubleshooting

### Issue: Changes not appearing on WordPress.org

**Solution:**
- WordPress.org caches plugin data
- Wait 15-30 minutes for cache to clear
- Force refresh browser cache (Ctrl+F5)

### Issue: Wrong version downloading

**Solution:**
- Check `Stable tag:` in trunk/readme.txt
- Must match the tag directory name exactly
- Commit any changes to trunk

### Issue: SVN Authentication Failed

**Solution:**
- Username: WordPress.org username
- Password: WordPress.org password (not API key)
- Save credentials in TortoiseSVN for future

### Issue: Conflict on SVN Update

**Solution:**
- Right-click conflicted file
- TortoiseSVN → Resolve Conflict
- Choose appropriate resolution
- Mark as resolved
- Commit

---

## SVN Commands Reference (Command Line Alternative)

If TortoiseSVN is unavailable, use command line:

### Initial Checkout
```bash
svn co https://plugins.svn.wordpress.org/markup-by-attribute-for-woocommerce
cd markup-by-attribute-for-woocommerce
```

### Update Trunk
```bash
svn update
# Copy files to trunk/
svn add trunk/* --force
svn commit -m "Update trunk for version 4.4.0"
```

### Create Tag
```bash
svn cp trunk tags/4.4.0
svn commit -m "Tagging version 4.4.0"
```

### Update Stable Tag
```bash
# Edit trunk/readme.txt
svn commit -m "Update stable tag to 4.4.0"
```

---

## Release Timeline

**Typical release schedule:**

1. **Monday:** Begin comprehensive testing (TEST_PLAN_4.4.0.md)
2. **Tuesday:** Complete testing, fix any issues found
3. **Wednesday Morning:**
   - Update version numbers
   - Final Git commit and merge
   - Create Git tag
4. **Wednesday Afternoon:**
   - SVN trunk update
   - Create SVN tag
   - Verify release on WordPress.org
5. **Wednesday Evening:** Monitor for issues
6. **Thursday+:** Monitor support forums, respond to users

---

## Version History Quick Reference

**Current Version:** 4.4.0
**Previous Version:** 4.3.9
**Next Planned Version:** TBD

**SVN Repository:** https://plugins.svn.wordpress.org/markup-by-attribute-for-woocommerce/
**Git Repository:** https://github.com/Mark-Tomlinson/markup-by-attribute-for-woocommerce
**Plugin Page:** https://wordpress.org/plugins/markup-by-attribute-for-woocommerce/

---

## Emergency Rollback Procedure

If critical issue discovered after release:

1. **Change Stable Tag Back**
   ```bash
   # Edit trunk/readme.txt
   Stable tag: 4.3.9  # Previous working version
   svn commit -m "Emergency rollback to 4.3.9"
   ```

2. **Users will now download 4.3.9**
   - Previous version still in tags/4.3.9
   - No need to delete 4.4.0 tag

3. **Fix Issue**
   - Fix problem in trunk
   - Test thoroughly
   - Release as 4.4.1

4. **Update Stable Tag to 4.4.1**
   ```bash
   Stable tag: 4.4.1
   svn commit -m "Update to fixed version 4.4.1"
   ```

---

*Created: November 20, 2025*
*By: Akina 🌸*
*For: WordPress.org Plugin Release Process*
