# Certificate System Implementation Plan

## 1. Create Missing Controllers
- [x] Create CertificateController with methods: index, createForAdmin, storeForAdmin, previewDesign, download, generateGroup, edit, finalize
- [x] Create CertificateRequestController with methods: store, approve, index
- [x] Create CertificateTemplateController with methods: index, create, store, preview

## 2. Create Admin Certificate Management Views
- [x] Create certificates/index.blade.php (list certificates, requests)
- [x] Create certificates/create_for_admin.blade.php (form to issue certificate to student/group)
- [x] Create certificates/edit.blade.php (edit certificate before finalizing)
- [x] Create certificates/preview.blade.php (preview certificate design)

## 3. Add Student Certificate Features
- [x] Add certificates section to student dashboard (request certificate, view issued certificates)
- [x] Create student certificate request form
- [x] Create student certificates view

## 4. Create Certificate Templates
- [x] Create blade template for individual/special certificate (badge-like)
- [x] Create blade template for group completion certificate
- [x] Implement PDF generation using dompdf

## 5. Implement PDF Generation and Download
 - [x] Review and fix any hard-coded secrets in repo
- [x] Test certificate creation for individuals
- [x] Test certificate creation for groups
- [x] Test student certificate requests
- [x] Test admin approval workflow
- [x] Fix 404 error in preview functionality
- [x] Add authorization checks for certificate access
