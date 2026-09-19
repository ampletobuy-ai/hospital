# Plan Role & Permission Matrix (Implementation Guide)

This document defines how **Starter / Business / Enterprise** (and trial) plans should map to hospital **modules, roles, and permissions** for implementation.

**Source of truth (pricing / feature flags):** `application/config/hospital_portal.php`  
**Permission catalog (tenant DB):** `permission_group`, `permission_category`, `roles`, `roles_permissions`  
**Current status:** Plan `features` are marketing-only today. Quotas are stored on subscription metadata. This matrix is the target for enforcement work.

---

## 1. Plans & quotas

| | Starter (`hospital_starter`) | Business (`hospital_business`) | Enterprise (`hospital_enterprise`) |
|--|--:|--:|--:|
| Target | Clinic / Nursing Home | 20–100 bed Hospital | 100+ bed / Multi-branch |
| Price/year (excl. GST) | ₹25,000 | ₹75,000 | ₹1,50,000 |
| Max staff (`no_of_staff` / `max_users`) | 5 | 15 | 50 |
| Max patients (`no_of_patient`) | 500 | 5,000 | 50,000 |
| Storage | 5 GB | 20 GB | 100 GB |
| Branches (`max_warehouses`) | 1 | 1 | 5 |
| Trial | — | 14-day trial uses **Business** limits/features | — |

Internal plan codes (keep stable for billing / central DB):

- `hospital_starter` → Starter  
- `hospital_business` → Business (also trial base plan)  
- `hospital_enterprise` → Enterprise  
- `trial` → provision as `hospital_business` + `status=trialing`

---

## 2. Product feature matrix (plan flags)

Legend: ✓ enabled · — disabled · text = tiered capability

| Feature key | Starter | Business | Enterprise |
|--|:--:|:--:|:--:|
| `opd` | ✓ | ✓ | ✓ |
| `patient_registration` | ✓ | ✓ | ✓ |
| `appointment` | ✓ | ✓ | ✓ |
| `billing` | ✓ | ✓ | ✓ |
| `ipd` | — | ✓ | ✓ |
| `bed_ward` | — | ✓ | ✓ |
| `pharmacy` | — | ✓ | ✓ |
| `laboratory` | — | ✓ | ✓ |
| `inventory` | basic | advanced | advanced |
| `tpa_insurance` | — | ✓ | ✓ |
| `doctor_commission` | — | ✓ | ✓ |
| `reports` | basic | advanced | advanced |
| `whatsapp_sms` | — | ✓ | ✓ |
| `multi_branch` | — | — | ✓ |
| `api_integration` | — | — | ✓ |
| `customization` | limited | standard | advanced |
| `support` | standard | priority | dedicated |

---

## 3. Feature → `permission_group` mapping

Use these `permission_group.short_code` values when enabling/disabling modules for a plan.

| Plan feature | Permission group(s) | Notes |
|--|--|--|
| `opd` | `opd` | Core OPD |
| `patient_registration` | `patient` | Patient master |
| `appointment` | `appointment` | Appointments / queue / shifts |
| `billing` | `bill`, `hospital_charges`, `income`, `expense` | Billing + charges + cash books |
| `ipd` | `ipd` | IPD patients, OT linked to IPD, discharge |
| `bed_ward` | `ipd` categories: `bed`, `bed_status`, `bed_type`, `bed_group`, `floor`, `bed_history` | Subset of IPD permissions |
| `pharmacy` | `pharmacy` | Medicines, purchase, pharmacy bill |
| `laboratory` | `pathology`, `radiology` | Lab + radiology |
| `inventory` | `inventory` | See §5 for basic vs advanced |
| `tpa_insurance` | `tpa_management` | Organisations / TPA charges |
| `doctor_commission` | `referral` | Referral commission / payments (closest existing module) |
| `reports` | `reports` | See §6 for basic vs advanced report list |
| `whatsapp_sms` | `communicate` (`email_sms`, `email_sms_log`), `system_settings` (`sms_setting`, `notification_setting`) | Channel settings + send |
| `multi_branch` | *(new / custom)* | Not a stock permission group; implement as tenant/branch switch + `max_warehouses` |
| `api_integration` | *(new / custom)* | API keys / webhooks; gate separately |
| Always useful (all plans) | `front_office`, `human_resource`, `dashboard_and_widgets`, `calendar_to_do_list`, `system_settings` (limited), `certificate`, `chat` | Scope HR/settings by customization tier |

### Related modules (recommended plan placement)

| Module group | Starter | Business | Enterprise | Rationale |
|--|:--:|:--:|:--:|--|
| `ambulance` | — | ✓ | ✓ | Hospital ops with Business+ |
| `blood_bank` | — | ✓ | ✓ | Same as laboratory tier |
| `live_consultation` | — | ✓ | ✓ | Optional add-on; default Business+ |
| `front_cms` | limited | ✓ | ✓ | Starter: view-only / minimal; Business+: full |
| `duty_roster` | — | ✓ | ✓ | Staff rostering |
| `annual_calendar` | ✓ | ✓ | ✓ | Lightweight |
| `download_center` | ✓ | ✓ | ✓ | Content share |

---

## 4. Role matrix (default staff roles)

Built-in roles (`roles` table):

| ID | Role |
|--|--|
| 1 | Admin |
| 2 | Accountant |
| 3 | Doctor |
| 4 | Pharmacist |
| 5 | Pathologist |
| 6 | Radiologist |
| 7 | Super Admin |
| 8 | Receptionist |
| 9 | Nurse |

### 4.1 Which roles exist / are assignable per plan

| Role | Starter | Business | Enterprise |
|--|:--:|:--:|:--:|
| Super Admin | ✓ (1 seat, provisioned) | ✓ | ✓ |
| Admin | ✓ | ✓ | ✓ |
| Receptionist | ✓ | ✓ | ✓ |
| Doctor | ✓ | ✓ | ✓ |
| Accountant | ✓ | ✓ | ✓ |
| Nurse | ✓ | ✓ | ✓ |
| Pharmacist | — | ✓ | ✓ |
| Pathologist | — | ✓ | ✓ |
| Radiologist | — | ✓ | ✓ |

On Starter provision: hide or disable assignment of Pharmacist / Pathologist / Radiologist (modules off).

### 4.2 Role × module access (when module is plan-enabled)

Legend: F = full CRUD (as today’s Super Admin/Admin grants) · R = operational use · — = no access

| Module | Super Admin | Admin | Receptionist | Doctor | Nurse | Accountant | Pharmacist | Pathologist | Radiologist |
|--|:--:|:--:|:--:|:--:|:--:|:--:|:--:|:--:|:--:|
| Patient | F | F | R | R | R | R (view bills) | R (dispense) | R | R |
| OPD | F | F | R | R | R | — | — | — | — |
| Appointment | F | F | F | R | R | — | — | — | — |
| Billing / Charges | F | F | R (collect) | — | — | F | R (pharmacy bill) | R (lab bill) | R (radio bill) |
| IPD / Bed | F | F | R | R | F | — | — | — | — |
| Pharmacy | F | F | — | R (view Rx) | — | — | F | — | — |
| Pathology | F | F | — | R | — | — | — | F | — |
| Radiology | F | F | — | R | — | — | — | — | F |
| Inventory | F | F | — | — | — | R | R (med stock) | — | — |
| TPA | F | F | R | — | — | F | — | — | — |
| Referral / commission | F | F | — | R | — | F | — | — | — |
| Reports | F | F | basic | own | limited | finance | pharmacy | pathology | radiology |
| Communicate / SMS | F | F | R | — | — | — | — | — | — |
| HR / Staff | F | F | — | — | — | payroll view | — | — | — |
| System Settings | F | limited | — | — | — | — | — | — | — |

Super Admin (`role_id=7`) always retains override within **plan-allowed** modules.

---

## 5. Inventory tiers

| Capability | `permission_category.short_code` | Starter (basic) | Business/Enterprise (advanced) |
|--|--|:--:|:--:|
| Item | `item` | ✓ | ✓ |
| Item Category | `item_category` | ✓ | ✓ |
| Issue Item | `issue_item` | ✓ | ✓ |
| Item Stock | `item_stock` | view / limited | ✓ |
| Store | `store` | — (single implicit) | ✓ |
| Supplier | `supplier` | — | ✓ |
| Inventory reports | `inventory_stock_report`, `add_item_report`, `issue_inventory_report`, `stock_report` | stock only | all |

---

## 6. Reports tiers

### Basic (Starter) — allow only

| short_code |
|--|
| `opd_report` |
| `opd_balance_report` |
| `appointment_report` |
| `patient_visit_report` |
| `patient_bill_report` |
| `daily_transaction_report` |
| `income_report` |
| `expense_report` |
| `staff_attendance_report` |

### Advanced (Business / Enterprise) — all of basic **plus**

| Area | short_code examples |
|--|--|
| IPD | `ipd_report`, `ipd_balance_report`, `discharge_patient_report` |
| Pharmacy | `pharmacy_bill_report`, `expiry_medicine_report`, `medicine_purchase_report`, `medicine_purchase_return_report` |
| Lab | `pathology_patient_report`, `pathology_balance_report`, `radiology_patient_report`, `radiology_balance_report` |
| Blood / Ambulance / OT | `blood_donor_report`, `blood_issue_report`, `component_issue_report`, `ambulance_report`, `ot_report` |
| TPA / Referral | `tpa_report`, `referral_report` |
| Finance deep | `all_transaction_report`, `balance_amount_report`, `processing_transaction_report`, `income_group_report`, `expense_group_report` |
| Inventory | `inventory_stock_report`, `add_item_report`, `issue_inventory_report`, `stock_report` |
| HR / Audit | `payroll_report`, `payroll_month_report`, `staff_day_wise_attendance_report`, `user_log`, `audit_trail_report`, `email_sms_log` |
| Other | `birth_report`, `death_report`, `live_consultation_report`, `live_meeting_report` |

---

## 7. System settings / customization tiers

| Setting / capability | short_code | Starter (limited) | Business (standard) | Enterprise (advanced) |
|--|--|:--:|:--:|:--:|
| General Setting | `general_setting` | ✓ | ✓ | ✓ |
| Prefix Setting | `prefix_setting` | ✓ | ✓ | ✓ |
| Notification Setting | `notification_setting` | ✓ | ✓ | ✓ |
| Payment Methods | `payment_methods` | ✓ | ✓ | ✓ |
| Languages / switcher | `languages`, `language_switcher` | ✓ | ✓ | ✓ |
| SMS Setting | `sms_setting` | — | ✓ | ✓ |
| Email Setting | `email_setting` | ✓ | ✓ | ✓ |
| Users | `users` | ✓ | ✓ | ✓ |
| Captcha | `captcha_setting` | ✓ | ✓ | ✓ |
| Symptoms / Finding / Vital / Operation masters | `symptoms_*`, `finding*`, `vital`, `operation*` | view | ✓ | ✓ |
| Custom Fields | `custom_fields` | — | ✓ | ✓ |
| ICD-10 | `icd10_groups`, `icd10_codes` | — | ✓ | ✓ |
| Theme Studio | `theme_studio` | — | — | ✓ |
| Front CMS Setting | `front_cms_setting` | — | ✓ | ✓ |
| Backup / Restore | `backup`, `restore` | — | ✓ | ✓ |
| Multi-branch admin | *(new)* | — | — | ✓ |
| API keys / webhooks | *(new)* | — | — | ✓ |

---

## 8. Billing permissions by plan

When `billing` is on (all plans), still hide bill types for disabled modules:

| `permission_category.short_code` | Starter | Business+ |
|--|:--:|:--:|
| `opd_billing`, `opd_billing_payment` | ✓ | ✓ |
| `appointment_billing` | ✓ | ✓ |
| `generate_bill`, `payment_receipt_header_footer` | ✓ | ✓ |
| `ipd_billing`, `ipd_billing_payment`, `generate_discharge_card` | — | ✓ |
| `pharmacy_billing`, `pharmacy_billing_payment` | — | ✓ |
| `pathology_billing`, `pathology_billing_payment` | — | ✓ |
| `radiology_billing`, `radiology_billing_payment` | — | ✓ |
| `blood_bank_billing`, `blood_bank_billing_payment` | — | ✓ |
| `ambulance_billing`, `ambulance_billing_payment` | — | ✓ |

Also disable OPD permission `opd_move_patient_in_ipd` on Starter.

---

## 9. Recommended enforcement model

### 9.1 Store plan features on subscription

On provision (already partially done), persist:

```json
{
  "signup_plan_code": "trial|hospital_starter|...",
  "quota": { "no_of_patient": 500, "no_of_staff": 5, "storage_mb": 5120 },
  "features": { "opd": true, "ipd": false, "...": "..." }
}
```

into `tenant_subscriptions.metadata` (and/or a dedicated JSON column).

### 9.2 Resolve effective features at runtime

Add something like `Plan_feature_gate`:

1. Load subscription for `session.tenant_id`  
2. Merge `metadata.features` with `hospital_portal.php` defaults for `plan_code`  
3. Expose helpers: `can('ipd')`, `can('pharmacy')`, `reportAllowed('ipd_report')`

### 9.3 Apply at three layers

| Layer | Behavior |
|--|--|
| **Sidebar / routes** | Hide menus whose `permission_group` is plan-disabled |
| **RBAC** | On login / role edit, intersect role permissions with plan-allowed permission IDs |
| **API / controllers** | Hard-check feature gate before create actions (don’t rely on UI alone) |
| **Quotas** | Keep using `SaasValidation` + `Subscription_resolver::quotaLimits()` |

### 9.4 Provision-time option (optional)

After cloning `roles_permissions`, strip permissions for plan-disabled groups so Super Admin templates match the plan. Re-apply on plan upgrade/downgrade.

### 9.5 Upgrade / downgrade

| Event | Action |
|--|--|
| Upgrade Starter → Business | Enable IPD/Pharmacy/Lab/TPA permission groups; unlock advanced reports |
| Downgrade | Hide modules; keep historical data read-only; block new IPD/Pharmacy records |
| Trial → paid | Keep Business features; switch status `trialing` → `active` |

---

## 10. Implementation checklist

### Phase 1 (done)

- [x] Persist `features` on `tenant_subscriptions.metadata` at signup  
- [x] `Plan_feature_gate` library (`application/libraries/Plan_feature_gate.php`)  
- [x] Sidebar filter via `Module_lib::hasActive` + plan module map (§3)  
- [x] RBAC gate via `Rbac::hasPrivilege` (including Super Admin) for plan-locked permission categories  
- [x] Controller guards for IPD / Pharmacy / Pathology / Radiology / TPA / Referral / Blood bank / Ambulance / Live consult  
- [x] Block enabling plan-locked modules in `Module::changeStatus`  
- [x] Disable `opd_move_patient_in_ipd` on Starter (via permission map → `ipd`)  
- [x] Flatten nested `metadata.quota` in `Subscription_resolver::quotaLimits`  

### Phase 2 (deferred)

- [x] Report menu filter (§6 — basic vs advanced short_code lists)  
- [x] Inventory permission subset (§5)  
- [x] Role assignment UI: hide Pharmacist/Pathologist/Radiologist on Starter (§4.1)  
- [x] Settings customization tiers (§7)  
- [x] Multi-branch + API as Enterprise-only custom gates *(API: gate plumbing only — `api_keys`/`webhooks` mapped; no admin UI yet; multi-branch via `max_warehouses` + Itemstore)*  
- [ ] Plan change job to recompute allowed permissions  
- [ ] Admin “Plan & limits” page showing current features + quotas  
- [ ] Provision-time `roles_permissions` strip  
- [ ] Tests: Starter cannot open IPD create; Business can; Enterprise can set multi-branch  

---

## 11. Reference files

| File | Role |
|--|--|
| `application/config/hospital_portal.php` | Plan prices, features, quotas |
| `application/libraries/Hospital_tenant_provisioning.php` | Writes subscription + metadata |
| `application/libraries/Plan_feature_gate.php` | Runtime feature / module / permission gate |
| `application/libraries/Module_lib.php` | Sidebar module active ∩ plan |
| `application/libraries/Rbac.php` | Privilege checks ∩ plan |
| `application/libraries/Subscription_resolver.php` | Status + quota limits |
| `application/libraries/SaasValidation.php` | Resource quota enforcement |
| `permission_group` / `permission_category` / `roles_permissions` | Existing RBAC catalog |
| `application/views/site/hospital_register.php` | Marketing compare table |

---

## 12. Summary

| Concern | Enforced today? | Target |
|--|:--:|--|
| Plan pricing / GST | ✓ | ✓ |
| Quotas (patients, staff, storage) | ✓ | ✓ |
| Module features (IPD, Pharmacy, …) | ✓ Phase 1 | ✓ |
| Role defaults per plan | ✓ Phase 2 §4.1 (assignable roles) | ✓ |
| Report / inventory / settings tiers | ✓ Phase 2 §5–§7 | ✓ |
| Multi-branch / API gates | ✓ Phase 2 (stores quota + API permission map) | Full branch switcher / API UI later |

This matrix is the contract for implementing plan-aware roles and permissions without changing billing plan codes.
