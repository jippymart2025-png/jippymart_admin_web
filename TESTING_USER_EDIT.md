# Testing Guide - User Edit Page (SQL Migration)

## Quick Test Instructions

### 1. Access the Edit Page
Navigate to the user edit URL:
```
http://127.0.0.1:8000/users/edit/vFxtj8dZ37TcOMRQWcRpuOyCtB03
```
(Replace the ID with any valid firebase_id from your users table)

### 2. Expected Behavior

#### On Page Load:
- ✅ Page should load without JavaScript errors
- ✅ Loading spinner should appear briefly
- ✅ User data should populate:
  - First Name field
  - Last Name field
  - Email field (disabled)
  - Country Code dropdown
  - Phone Number field (disabled)
  - Profile Picture (if exists)
  - Active checkbox
- ✅ Top cards should show:
  - Total Orders count
  - Wallet Amount with currency symbol

#### On Form Edit:
- ✅ Can edit First Name
- ✅ Can edit Last Name
- ✅ Can change Country Code
- ✅ Can upload new profile picture
- ✅ Can toggle Active status
- ✅ Email and Phone fields are disabled (read-only)

#### On Save:
- ✅ Validation errors appear if fields are empty
- ✅ Success: Redirects to `/users` list
- ✅ Error: Shows error message and stays on page
- ✅ Changes are reflected in database

### 3. Browser Console Check

Open browser console (F12) and look for:

**Success indicators:**
```javascript
✅ User data loaded: {status: true, data: {...}}
✅ User updated successfully: {status: true, ...}
```

**Error indicators (should NOT appear):**
```javascript
❌ Failed to load user data
❌ Error updating user
❌ Firebase is not defined
```

### 4. Database Verification

After saving, check the database:
```sql
SELECT id, firebase_id, firstName, lastName, email, phoneNumber, 
       countryCode, active, isActive, profilePictureURL 
FROM users 
WHERE firebase_id = 'vFxtj8dZ37TcOMRQWcRpuOyCtB03';
```

Changes should be reflected in the database.

### 5. API Testing (Optional)

You can test the API endpoints directly:

#### Get User Data:
```bash
curl -X GET "http://127.0.0.1:8000/api/app-users/vFxtj8dZ37TcOMRQWcRpuOyCtB03"
```

Expected response:
```json
{
  "status": true,
  "data": {
    "id": "vFxtj8dZ37TcOMRQWcRpuOyCtB03",
    "firebase_id": "vFxtj8dZ37TcOMRQWcRpuOyCtB03",
    "firstName": "John",
    "lastName": "Doe",
    "email": "john@example.com",
    "phoneNumber": "1234567890",
    "countryCode": "+1",
    "wallet_amount": 0,
    "profilePictureURL": "...",
    "active": true,
    "isActive": true,
    "createdAt": "2024-01-01 12:00:00",
    "totalOrders": 5,
    "provider": "email",
    "role": "customer"
  }
}
```

#### Update User:
```bash
curl -X PUT "http://127.0.0.1:8000/api/app-users/vFxtj8dZ37TcOMRQWcRpuOyCtB03" \
  -H "Content-Type: application/json" \
  -H "X-CSRF-TOKEN: YOUR_TOKEN" \
  -d '{
    "firstName": "Jane",
    "lastName": "Smith",
    "countryCode": "+1",
    "phoneNumber": "9876543210",
    "active": true
  }'
```

Expected response:
```json
{
  "status": true,
  "message": "User updated successfully",
  "data": {
    "id": "vFxtj8dZ37TcOMRQWcRpuOyCtB03",
    "firstName": "Jane",
    "lastName": "Smith",
    "email": "john@example.com"
  }
}
```

### 6. Common Issues & Solutions

#### Issue: "User not found" error
**Solution:** 
- Verify the ID exists in the database
- Check if `firebase_id` column has the correct value
- Try using the numeric `id` instead

#### Issue: Image upload not working
**Solution:**
- Ensure `storage/app/public/users/` directory exists
- Run: `php artisan storage:link`
- Check file permissions

#### Issue: Validation errors on save
**Solution:**
- First Name is required
- Last Name is required
- Phone Number is required (client-side validation)

#### Issue: Currency not displaying correctly
**Solution:**
- Current implementation uses default currency ($)
- Can be enhanced to load from database settings

### 7. Edge Cases to Test

- [ ] Edit user with no profile picture
- [ ] Edit user with existing profile picture
- [ ] Upload new profile picture (replaces old one)
- [ ] Edit user and remove First/Last name (should show error)
- [ ] Edit user with different country codes
- [ ] Edit user and toggle Active status multiple times
- [ ] Edit user that has orders (totalOrders > 0)
- [ ] Edit user with wallet balance

### 8. Rollback (If Needed)

If something goes wrong, you can revert by:

1. Restore `resources/views/settings/users/edit.blade.php` from git
2. Remove the new methods from `AppUserController.php`:
   - `show()`
   - `update()`
3. Remove the routes from `routes/api.php`:
   - `Route::get('/app-users/{id}')`
   - `Route::put('/app-users/{id}')`

### 9. Performance Notes

- API response time should be < 200ms for single user fetch
- Image upload may take 1-2 seconds depending on size
- No Firebase queries = faster load times

### 10. Security Notes

- ✅ CSRF token is included in all requests
- ✅ Email field is disabled (cannot be changed)
- ✅ Phone field is disabled (cannot be changed)
- ✅ Password is not exposed in API response
- ✅ SQL injection protected by Eloquent ORM

---

## Success Criteria

All these should work:
- ✅ Page loads user data from SQL
- ✅ Form fields are populated correctly
- ✅ Save button updates data in SQL
- ✅ No Firebase/Firestore references in console
- ✅ No JavaScript errors
- ✅ Data persists after page reload

---

## Support

If you encounter any issues:
1. Check browser console for errors
2. Check Laravel logs: `storage/logs/laravel.log`
3. Check network tab (F12 → Network) for failed API calls
4. Verify database connection is working
5. Ensure all migrations are run

---

**Date:** 2024  
**Status:** ✅ Migration Complete  
**Version:** SQL-Based User Management

