# SWMS Complete Workflow Architecture
## Meter Reading → Verification → Billing → Citizen Portal → Payment

### BUSINESS PROCESS FLOW

```
METER READER                    BILLING OFFICER                   SYSTEM                      CITIZEN
══════════════                  ═══════════════                   ═══════                    ═══════
                                                                                           
Login to System                Login to System                                           
↓                               ↓                                                         
View Assigned Consumers        View Pending Readings                                      
↓                               ↓                                                         
Select Consumer                Review Reading Details                                     
↓                               ↓                                                         
Verify Consumer Info           ├── Review Current Reading                                 
↓                               ├── Review Previous Reading                               
Enter Current Reading          ├── Review Consumption                                     
↓                               ├── Review Meter Photo                                    
Upload Meter Photo             ├── Review GPS Location                                    
↓                               ↓                                                         
Capture GPS Location           Decision:                                                  
↓                               ├── APPROVE ──→ is_verified=1, reading_status='verified'  
Submit Reading                 │              ↓                                           
↓                               │              Update meter.last_reading                  
Status=Pending Verification    │              Create reading_verifications record          
↓                               │              Status = Pending Verification → Verified   
══════════════                  │                                                        
                                ├── REJECT ──→ reading_status='rejected'                  
                                │              ↓                                           
                                │              Create reading_verifications record         
                                │              Send back to meter reader for correction    
                                │                                                        
                                └── After all readings verified:                          
                                     ↓                                                    
                                     Generate Bills STEP 3                                
                                     ═══════════════════                                  
                                     ↓                                                    
                                     For each verified reading:                           
                                     ├── Calculate consumption                             
                                     ├── Apply tariff rules (min 10=NPR 150)              
                                     ├── Generate bill number                              
                                     ├── Calculate charges                                 
                                     ├── Generate due date                                 
                                     ├── Save bill (status=unpaid)                         
                                     └── Link to reading_id                                
                                     ↓                                                    
══════════════════════════      Publish Bills to Citizen Portal                           
                                ─────────────────────────────                             
                                ↓                                                         
                                Set published_at timestamp                                 
                                Create bill_notifications record                          
══════════════════════════                                                                 
                                                                                          
                                                                 Login to Citizen Portal  
                                                                 ↓                        
                                                                 View Dashboard           
                                                                 ├── Unpaid Bills Count    
                                                                 ├── Total Due Amount      
                                                                 ├── Last Bill Alert       
                                                                 ├── Recent Payments       
                                                                 └── Account Info          
                                                                                        
                                                                 View Bill Details        
                                                                 ├── Bill Number           
                                                                 ├── Billing Period        
                                                                 ├── Previous Reading      
                                                                 ├── Current Reading       
                                                                 ├── Consumption Units     
                                                                 ├── Charge Breakdown      
                                                                 ├── Total Amount          
                                                                 ├── Due Date              
                                                                 └── Status Badge           
                                                                                        
                                                                 Make Payment             
                                                                 ├── Select Payment Method 
                                                                 │   ├── eSewa            
                                                                 │   ├── Khalti           
                                                                 │   ├── Fonepay          
                                                                 │   └── QR Payment       
                                                                 ├── Select Bills to Pay   
                                                                 ├── Process Payment       
                                                                 ├── Verify Transaction    
                                                                 ├── Generate Receipt      
                                                                 └── Update Bill Status    
                                                                                        
                                                                 Download Receipt         
                                                                 ├── Receipt Number        
                                                                 ├── Payment Details       
                                                                 ├── Bill Details          
                                                                 ├── Amount in Words       
                                                                 └── Print/PDF Download    
```

### USER JOURNEY

**Meter Reader Journey:**
1. Login → Dashboard → Click "Enter Reading" or "POS Reading"
2. Search consumer by name, consumer no, mobile, or meter no
3. View consumer info (name, address, meter details, last reading)
4. Enter current meter reading → System auto-calculates consumption
5. Flag unusual consumption (high/low/zero)
6. Capture meter photo using camera or upload
7. Capture GPS location (auto-detect or manual entry)
8. Add remarks if needed
9. Submit reading → Status = "Pending Verification"
10. System logs audit trail entry

**Billing Officer Journey:**
1. Login → Click "Verify Readings"
2. View all pending readings with consumer details
3. Filter by status (pending, verified, rejected)
4. Review each reading:
   - Compare current vs previous reading
   - Review consumption trend
   - View meter photo
   - Verify GPS location (click Google Maps link)
5. Decision:
   - **APPROVE**: Reading becomes verified, meter last_reading updated
   - **REJECT**: Select reason (inaccurate, photo unclear, GPS mismatch, etc.), add remarks, reading goes back for correction
6. After all readings verified → Generate bills
7. Click "Generate Bills" → Select fiscal year, billing period, due date
8. Preview bills → Verify amounts match tariff rules
9. Generate bills → Status = "Unpaid"
10. Publish bills to citizen portal → SMS/Email notification sent

**Citizen Journey:**
1. Login to Citizen Portal (consumer_no + password/OTP)
2. Dashboard shows: Unpaid bills count, total due, last bill alert
3. View "My Bills" → List of all bills
4. Click bill → View full details: readings, charges, due date
5. Print/Download bill as PDF
6. Click "Pay Now" → Select payment method:
   - eSewa: Redirect to eSewa payment gateway
   - Khalti: Khalti checkout popup
   - Fonepay: Redirect to Fonepay
   - QR: Show QR code for scanning
7. Select bills to pay (one or multiple)
8. Complete payment → Receipt generated
9. View/Print/Download receipt
10. Bill status updated to "Paid"

### DATABASE RELATIONSHIPS (ER)

```
consumers ──────< bills ──────< bill_payments >────── payments
consumers ──────< meters ──────< meter_readings ──────< reading_verifications
meters ──────< meter_readings ──────< reading_documents
fiscal_years ──────< bills
tariffs ──────< bills
bills ──────< bill_notifications
payments ──────< payment_reconciliation
users ──────< meter_readings (read_by, verified_by)
users ──────< reading_verifications (verified_by)
users ──────< bills (generated_by)
users ──────< payments (received_by)
```

### TARIFF RULES (as implemented in BillingEngine::calculateBillAmount)

```
Consumption <= 10 Units → Bill Amount = NPR 150 (Minimum Charge)
Consumption > 10 Units  → Bill Amount = NPR 150 + ((Consumption - 10) × NPR 10)

Examples:
  5 Units  → NPR 150
 10 Units  → NPR 150
 15 Units  → NPR 200
 20 Units  → NPR 250
 25 Units  → NPR 300
 50 Units  → NPR 550
100 Units  → NPR 1,050
```

### BILL STATUS WORKFLOW

```
Draft ──→ Pending Verification ──→ Verified ──→ Bill Generated ──→ Published ──→ Unpaid ──→ Paid
                ↓ rejected                                                            
          Pending Correction ──→ Resubmitted ──→ Verified (re-verify)
```

### SECURITY LAYERS

1. **Authentication**: Session-based with CSRF token validation
2. **Authorization**: RBAC with role-based permissions (super_admin, billing_officer, meter_reader, citizen, accountant)
3. **Input Validation**: Validator class for all inputs
4. **Output Escaping**: `escape()` function for XSS prevention
5. **SQL Injection**: Prepared statements via `db()->fetchAll()`, `db()->fetchOne()`
6. **File Uploads**: Allowed extensions check, size limits
7. **Transaction Safety**: `db()->beginTransaction()` / `db()->commit()` / `db()->rollback()` for all write operations
8. **Audit Trail**: `log_activity()` for all CRUD operations
9. **Rate Limiting**: Login attempt tracking in session

### TESTING CHECKLIST

**Meter Reading (STEP 1):**
- [ ] Search consumer by consumer_no, name, mobile, meter_no
- [ ] Verify consumer info displays correctly
- [ ] Previous reading loads from last_verified reading
- [ ] Enter current reading → consumption calculated
- [ ] High/low consumption flagged correctly
- [ ] Meter photo upload works (camera + file)
- [ ] GPS auto-capture works (with permission)
- [ ] Manual GPS entry works
- [ ] Submit reading with valid data
- [ ] Submit with current < previous fails
- [ ] Submit with inactive meter fails
- [ ] Submitted reading status = "pending_verification"
- [ ] Saving without GPS/photo still succeeds
- [ ] Estimated reading flag works

**Reading Verification (STEP 2):**
- [ ] Pending readings load correctly
- [ ] Filter by status (pending/verified/rejected) works
- [ ] Search by consumer/meter works
- [ ] Approve reading → status changes to "verified"
- [ ] Approve updates meter.last_reading
- [ ] Reading verification audit record created
- [ ] Reject with reason → status = "rejected"
- [ ] Rejection reason saved
- [ ] GPS link opens Google Maps
- [ ] Photo modal opens
- [ ] Already verified reading cannot be re-approved
- [ ] Pagination works for large datasets
- [ ] Stats counters correct

**Bill Generation (STEP 3):**
- [ ] Only verified readings appear in preview
- [ ] Unverified consumers excluded
- [ ] Existing bills for period skipped
- [ ] Preview shows correct tariff calculation
- [ ] Consumption <= 10 → NPR 150
- [ ] Consumption > 10 → correct progressive rate
- [ ] Bill generation creates valid bills
- [ ] Generated bills have correct period
- [ ] Generated bills linked to reading_id
- [ ] Bill number format correct
- [ ] Due date calculated correctly
- [ ] Generation creates audit trail
- [ ] Auto-generate with no readings gives empty preview

**Citizen Portal (STEPS 4-5):**
- [ ] Login with valid consumer credentials
- [ ] Dashboard shows correct stats
- [ ] Unpaid bills alert shows
- [ ] Bills list paginated correctly
- [ ] Bill detail shows all charge breakdown
- [ ] Print/PDF link opens in new tab
- [ ] Status badges display correctly
- [ ] Overdue bills marked in red
- [ ] Payment history loads correctly
- [ ] Receipt download for each payment
- [ ] Quick action buttons work

**Online Payment (STEP 6):**
- [ ] "Pay Now" from bill detail works
- [ ] "Pay Now" from bills list works
- [ ] eSewa payment initiation redirects correctly
- [ ] Khalti checkout popup opens
- [ ] Fonepay form redirect works
- [ ] QR payment page shows
- [ ] Multiple bills can be selected
- [ ] Total amount auto-calculates
- [ ] Payment method selection UX works
- [ ] Payment without selecting method blocked
- [ ] Payment without selecting bills blocked
- [ ] eSewa callback processes payment
- [ ] Khalti callback processes payment
- [ ] QR confirmation processes payment
- [ ] Bill status updated to "paid" after payment
- [ ] Payment receipt generated
- [ ] Duplicate payment prevented
- [ ] Failed payment shows error message

**Receipt Generation (STEP 7):**
- [ ] Receipt shows correct payment details
- [ ] Receipt shows bill details
- [ ] Amount in words correct
- [ ] Print layout clean and well-formatted
- [ ] Auto-print on page load for print mode
- [ ] Receipt accessible from payment history
- [ ] Bill receipt accessible from bills page
- [ ] PDF download works (Ctrl+P → Save as PDF)
- [ ] Receipt for multiple bills shows all bills
- [ ] Discount/penalty waived shows correctly
- [ ] Consumer details correct on receipt

**Permissions & Security:**
- [ ] Meter reader cannot approve readings
- [ ] Meter reader cannot generate bills
- [ ] Billing officer can verify + generate
- [ ] Citizen can only see own bills
- [ ] Citizen cannot access admin pages
- [ ] CSRF token validates on all POST
- [ ] SQL injection prevented in all queries
- [ ] XSS prevented with escape()
- [ ] File upload restricts to allowed types
- [ ] Auth check on all pages
- [ ] Session timeout works

**Database & Migrations:**
- [ ] Migration 006 runs without errors
- [ ] Migration 007 runs without errors
- [ ] Migration 008 runs without errors
- [ ] All tables created with correct schema
- [ ] Foreign keys reference correct tables
- [ ] Indexes created for performance
- [ ] Rollback on transaction failure works
- [ ] Permission seeds created correctly
