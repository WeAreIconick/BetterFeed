# REST API URL Validation Rules
## NEVER REPEAT THE URL ERROR AGAIN!

### 🚨 CRITICAL: REST API URL PATTERNS MUST BE ABSOLUTE

**NEVER USE THESE PATTERNS:**
```javascript
// ❌ WRONG - Translates to /wp-admin/wp-json/... 
fetch('./wp-json/...')

// ❌ WRONG - Becomes wrong path in admin
fetch('../wp-json/...')

// ❌ WRONG - Missing leading slash
fetch('wp-json/...')

// ❌ WRONG - Uses admin path instead of API path
fetch('/wp-admin/wp-json/...')
```

**ALWAYS USE THESE PATTERNS:**
```javascript
// ✅ CORRECT - Always absolute from root
fetch('/wp-json/betterfeed/v1/endpoint')

// ✅ CORRECT - WordPress REST API format
fetch('/wp-json/{namespace}/v1/{route}')
```

### 🛡️ REST API URL VALIDATION RULES

1. **MANDATORY URL PATTERNS:**
   - All REST API calls MUST start with `/wp-json/`
   - NEVER use relative paths (`./wp-json/` or `../wp-json/`)
   - NEVER use `/wp-admin/wp-json/` (this is wrong!)
   - Always use absolute URLs from root

2. **NAMESPACE FORMAT:**
   - Plugin endpoints MUST use `/wp-json/betterfeed/v1/`
   - Version MUST be included (`/v1/` is mandatory)
   - Follow WordPress REST API naming conventions

3. **TESTING REQUIREMENTS:**
   - Every fetch() call MUST be tested manually in browser
   - Check Network tab for 404 vs 401 errors
   - 404 = Wrong URL path, 401 = Authentication issue

### 🔍 ERROR PREVENTION CHECKLIST

**Before ANY fetch() call:**
- [ ] URL starts with `/wp-json/` (not `./wp-json/`)
- [ ] Includes namespace `/betterfeed/`
- [ ] Includes version `/v1/`
- [ ] Uses correct HTTP method (GET/POST/PUT/DELETE)
- [ ] Includes `credentials: 'include'` for auth
- [ ] Includes `X-WP-Nonce` header
- [ ] Has proper error handling with `.catch()`

### 🚨 COMMON REST API URL ERRORS TO PREVENT

1. **Relative Path Disasters:**
   ```javascript
   // ❌ This becomes /wp-admin/wp-json/ in admin context
   fetch('./wp-json/betterfeed/v1/clear-cache')
   
   // ✅ This is always correct regardless of context
   fetch('/wp-json/betterfeed/v1/clear-cache')
   ```

2. **Missing Version:**
   ```javascript
   // ❌ Missing version - WordPress won't route
   fetch('/wp-json/betterfeed/clear-cache')
   
   // ✅ Includes version - WordPress routes correctly
   fetch('/wp-json/betterfeed/v1/clear-cache')
   ```

3. **Wrong Admin Path:**
   ```javascript
   // ❌ Wrong - This is admin URL, not REST API
   fetch('/wp-admin/wp-json/...')
   
   // ✅ Correct - WordPress REST API endpoint
   fetch('/wp-json/...')
   ```

4. **Context-Dependent URLs:**
   ```javascript
   // ❌ Wrong - Only works on some pages
   fetch('wp-json/betterfeed/v1/clear-cache')
   
   // ✅ Correct - Works everywhere
   fetch('/wp-json/betterfeed/v1/clear-cache')
   ```

### 🎯 BETTERFEED SPECIFIC ENDPOINTS

**All these endpoints must use ABSOLUTE URLs:**

```javascript
// Cache Management
fetch('/wp-json/betterfeed/v1/clear-cache', { method: 'POST' })
fetch('/wp-json/betterfeed/v1/warm-cache', { method: 'POST' })

// Analytics
fetch('/wp-json/betterfeed/v1/export-analytics?format=csv&days=30')

// Presets
fetch('/wp-json/betterfeed/v1/apply-preset', { method: 'POST', body: JSON.stringify({preset: 'name'}) })
```

### 🧪 MANUAL TESTING PROTOCOL

**For every endpoint:**

1. **Open Browser DevTools Network Tab**
2. **Click the button/make the call**
3. **Check the Request URL in Network tab:**
   - ✅ Should show: `/wp-json/betterfeed/v1/endpoint`
   - ❌ If shows: `/wp-admin/wp-json/betterfeed/v1/endpoint` = WRONG!
4. **Check Response Status:**
   - ✅ 200 OK = Working correctly
   - ✅ 401 Unauthorized = Need authentication (normal)
   - ❌ 404 Not Found = Wrong URL path (ERROR!)

### 📝 ADDITIONAL NOTES

- **WordPress admin pages:** Run on `/wp-admin/` URLs
- **REST API endpoints:** Live on `/wp-json/` URLs  
- **Never mix:** `/wp-admin/wp-json/` doesn't exist!
- **Always absolute:** `/` prefix ensures consistent behavior

This rule prevents the exact 404 error we experienced with `/wp-admin/wp-json/` calls.
