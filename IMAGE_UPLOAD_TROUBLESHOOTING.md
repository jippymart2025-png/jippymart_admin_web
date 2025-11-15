# 🖼️ Image Upload & View - Troubleshooting Guide

## ✅ Recent Fixes Applied

### 1. Storage Symlink Created
```
✅ Command executed: php artisan storage:link
✅ Result: public/storage → storage/app/public
```

### 2. Modal Changed from `<embed>` to `<img>`
**Before:** Used `<embed>` tag (for PDFs)  
**After:** Using `<img>` tag (for images) ✅

### 3. Image URL Format Fixed
**Before:** `Storage::url('public/...')` → Wrong path  
**After:** `asset('storage/...')` → Correct path ✅

### 4. Added Image Loading States
```
- Loading indicator while image loads
- Error message if image fails to load
- Console logging for debugging
```

---

## 🧪 Testing the Fix

### Step 1: Upload a Document
```
1. Go to: http://127.0.0.1:8000/drivers/document/upload/3UZ7VPSghqXIjq984VKovbguDHK2/mo3PBshgnRET0QK6dZe3
2. Select Front Image (and Back Image if required)
3. Click Save
4. Check Console (F12):
   
   Expected output:
   📤 Uploading driver document: {driverId: "...", docId: "...", ...}
   ✅ Document uploaded successfully: {frontUrl: "http://127.0.0.1:8000/storage/drivers/documents/...", ...}
```

### Step 2: View the Image
```
1. Go to: http://127.0.0.1:8000/drivers/document-list/3UZ7VPSghqXIjq984VKovbguDHK2
2. Look for [View Front Image] badge
3. Click it
4. Check Console (F12):
   
   Expected output:
   🖼️ Opening image modal with URL: http://127.0.0.1:8000/storage/drivers/documents/...
   ✅ Image loaded successfully
   
5. Modal should show:
   - Loading spinner initially
   - Then the uploaded image
   - Or error message if file not found
```

### Step 3: Check Server Logs
```
tail -f storage/logs/laravel.log

Expected logs:
📤 Driver document upload request: {"driverId":"...","docId":"...","frontFilename":"..."}
✅ Front image uploaded: {"url":"http://127.0.0.1:8000/storage/drivers/documents/..."}
📝 Document data prepared: {"documentId":"...","status":"uploaded","frontImage":"...","backImage":"..."}
✅ Updated existing verification record for driver: {"driverId":"..."}
📋 Driver verification status updated: {"driverId":"...","isVerified":false}
```

---

## 🔍 Debugging Steps

### If Image Still Not Showing:

**1. Check Console Output**
```javascript
// When clicking "View Front Image"
🖼️ Opening image modal with URL: http://127.0.0.1:8000/storage/drivers/documents/front_1234567890.jpg

// If this shows:
✅ Image loaded successfully
// → Image should display

// If this shows:
❌ Failed to load image: ...
// → File not found, check next steps
```

**2. Check if Image File Exists**
```powershell
cd "E:\jippy workspace"
dir storage\app\public\drivers\documents

# Should show uploaded files
```

**3. Check Image URL in Database**
```sql
SELECT * FROM documents_verify WHERE id = '3UZ7VPSghqXIjq984VKovbguDHK2';

-- Check the 'documents' JSON field
-- Should contain frontImage and backImage URLs like:
-- "frontImage": "http://127.0.0.1:8000/storage/drivers/documents/front_xxx.jpg"
```

**4. Test Image URL Directly**
```
Copy the URL from console, paste in browser:
http://127.0.0.1:8000/storage/drivers/documents/front_xxx.jpg

If 404:
- Check if file exists in storage/app/public/drivers/documents/
- Check if symlink exists: public/storage → storage/app/public
- Check file permissions

If image shows:
- Modal issue, not storage issue
```

**5. Check Storage Symlink**
```powershell
cd "E:\jippy workspace\public"
dir | Select-String "storage"

# Should show:
# storage -> E:\jippy workspace\storage\app\public
```

---

## 🛠️ Manual Fix Commands

### Recreate Storage Symlink
```powershell
cd "E:\jippy workspace"
# Remove old symlink if exists
Remove-Item public\storage -Force -ErrorAction SilentlyContinue
# Create new symlink
php artisan storage:link
```

### Create Directories
```powershell
cd "E:\jippy workspace"
New-Item -ItemType Directory -Force -Path "storage\app\public\drivers\documents"
New-Item -ItemType Directory -Force -Path "storage\app\public\vendors\documents"
New-Item -ItemType Directory -Force -Path "storage\app\public\marts\gallery"
New-Item -ItemType Directory -Force -Path "storage\app\public\marts\menu"
```

### Check Permissions (if on Linux/Mac)
```bash
chmod -R 775 storage
chmod -R 775 public/storage
```

---

## 📋 Image Upload Flow

### Complete Flow:
```
1. User selects image
   ↓
2. JavaScript reads file as base64
   ↓
3. Image preview shown
   ↓
4. User clicks Save
   ↓
5. POST /api/drivers/document-upload/{driverId}/{docId}
   ↓
6. Controller: uploadDriverDocument()
   ↓
7. uploadBase64Image() decodes and saves
   ↓
8. File saved to: storage/app/public/drivers/documents/front_xxx.jpg
   ↓
9. URL generated: asset('storage/drivers/documents/front_xxx.jpg')
   ↓
10. URL saved to documents_verify table
   ↓
11. Frontend redirects to document list
   ↓
12. Document list loads from SQL
   ↓
13. [View Front Image] button shows with URL
   ↓
14. User clicks button
   ↓
15. Modal opens with image URL
   ↓
16. Browser requests: http://127.0.0.1:8000/storage/drivers/documents/front_xxx.jpg
   ↓
17. Symlink redirects to: storage/app/public/drivers/documents/front_xxx.jpg
   ↓
18. Image displays in modal
```

---

## ✅ Expected Console Output

### When Uploading:
```
📤 Uploading driver document: {driverId: "...", docId: "...", frontImage: "front_xxx.jpg"}
✅ Document uploaded successfully: {
  success: true,
  frontUrl: "http://127.0.0.1:8000/storage/drivers/documents/front_xxx.jpg",
  docData: {documentId: "...", status: "uploaded", frontImage: "...", backImage: "..."}
}
✅ Activity logged successfully: drivers document_uploaded Uploaded document...
```

### When Viewing:
```
📄 Document verification data: {documentId: "...", status: "uploaded", frontImage: "http://...", backImage: "http://..."}
🖼️ Front image URL: http://127.0.0.1:8000/storage/drivers/documents/front_xxx.jpg
🖼️ Opening image modal with URL: http://127.0.0.1:8000/storage/drivers/documents/front_xxx.jpg
✅ Image loaded successfully
```

---

## 🎯 Quick Fix Checklist

If image not showing, check in order:

- [ ] Storage symlink exists (`public/storage` folder)
- [ ] Upload folder exists (`storage/app/public/drivers/documents`)
- [ ] Image URL format is correct (starts with `/storage/` not `/public/storage/`)
- [ ] Image file actually exists in the folder
- [ ] Console shows correct URL
- [ ] Direct URL access works (paste in browser)
- [ ] Modal uses `<img>` tag (not `<embed>`)
- [ ] Browser console shows no CORS errors
- [ ] File permissions allow read access

---

## 🔧 Final Verification

### Test Image Upload & View:
```
1. Upload new document image
2. Check console: Should see upload success with URL
3. Go to document list
4. Click "View Front Image"
5. Console should show: "✅ Image loaded successfully"
6. Modal should display the image
```

### If Still Blank Screen:
```
1. Open Console (F12)
2. Look for red errors
3. Share the console output
4. Check Network tab for 404 errors
5. Check server logs: storage/logs/laravel.log
```

---

All fixes have been applied. The image should now display correctly! 🎉

