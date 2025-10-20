# HOD Assignment System - Data Flow Diagrams (DFD)

## Overview

This document contains Data Flow Diagrams (DFD) for the HOD Assignment System, illustrating the flow of data through the system at different levels of abstraction.

## Context Diagram (Level 0 DFD)

| Component Type | Component Name | Description | Data Flow Direction |
|---------------|----------------|-------------|-------------------|
| **External Entity** | Admin User | System administrator who manages HOD assignments | → System |
| **External Entity** | Web Browser | Client interface for system interaction | ↔ System |
| **Process** | HOD Assignment System | Main system processing assignments | Central |
| **Data Store** | Database Server | MySQL database storing users, departments, lecturers | ←→ System |
| **Data Store** | Redis Cache | High-performance caching layer | ←→ System |
| **Data Store** | Log Files | Audit trails and error logs | ← System |
| **Trust Boundary** | Email Notifications | External email service | ← System |
| **Trust Boundary** | User Accounts | User management system | ←→ System |

## Level 1 DFD - Main System Processes

| Process ID | Process Name | Description | Input Data | Output Data | Data Stores Used |
|------------|--------------|-------------|------------|-------------|------------------|
| **P1.0** | Authentication Process | Validates admin credentials and establishes session | Username, Password, CSRF Token | Session Token, User Role | Users Table |
| **P1.1** | Department Management | CRUD operations for departments | Department Data, User Permissions | Department Records | Departments Table |
| **P1.2** | Lecturer Management | CRUD operations for lecturers | Lecturer Data, Department Assignment | Lecturer Records | Lecturers Table, Users Table |
| **P1.3** | HOD Assignment Process | Core assignment functionality | Department ID, Lecturer ID | Assignment Records | Departments Table, Users Table |
| **P1.4** | Reports & Statistics | Data analysis and reporting | Assignment Data, Filters | Statistics, Reports | All Tables, Cache |

### Data Flow Table

| Data Flow ID | Source | Destination | Data Elements | Frequency | Volume |
|--------------|--------|-------------|---------------|-----------|--------|
| **DF1.1** | Admin User | Authentication Process | Login Credentials | Per Session | Low |
| **DF1.2** | Authentication Process | Main Dashboard | Session Token | Per Login | Low |
| **DF1.3** | Main Dashboard | Department Management | CRUD Requests | On Demand | Medium |
| **DF1.4** | Main Dashboard | Lecturer Management | CRUD Requests | On Demand | Medium |
| **DF1.5** | Main Dashboard | HOD Assignment Process | Assignment Requests | On Demand | Low |
| **DF1.6** | Main Dashboard | Reports & Statistics | Report Requests | On Demand | Medium |
| **DF1.7** | Department Management | Departments Table | Department Records | On Demand | Medium |
| **DF1.8** | Lecturer Management | Lecturers Table | Lecturer Records | On Demand | Medium |
| **DF1.9** | HOD Assignment Process | Departments Table | HOD Assignments | On Demand | Low |
| **DF1.10** | Reports & Statistics | Assignment Stats Cache | Statistics Data | On Demand | Medium |

## Level 2 DFD - HOD Assignment Process

| Process ID | Process Name | Description | Input Data | Output Data | Validation Rules |
|------------|--------------|-------------|------------|-------------|------------------|
| **P2.1** | Select Department | Admin chooses department from dropdown | Department ID | Selected Department | Must exist in database |
| **P2.2** | Validate Department | Check department validity and permissions | Department Record | Validation Result | Active status, admin access |
| **P2.3** | Load Available Lecturers | Retrieve eligible lecturers | Department ID | Lecturer List | Role in (lecturer, hod) |
| **P2.4** | Filter Lecturers | Apply department and availability filters | Raw Lecturer List | Filtered List | Department match, active status |
| **P2.5** | Select Lecturer | Admin chooses lecturer for assignment | Lecturer ID | Selected Lecturer | Must exist, valid permissions |
| **P2.6** | Validate Lecturer | Check lecturer eligibility | Lecturer Record | Validation Result | Active status, complete profile |
| **P2.7** | Check Conflicts | Verify no conflicting assignments | Current Assignments | Conflict Report | Unique HOD per department |
| **P2.8** | Handle Reassignment | Process lecturer already assigned elsewhere | Conflict Data | Resolution Action | Remove old assignment |
| **P2.9** | Create User Account | Generate user account for new HOD | Lecturer Data | User Account | Unique username/email |
| **P2.10** | Update Department HOD | Set HOD for selected department | Department ID, User ID | Updated Department | Transaction integrity |
| **P2.11** | Update User Role | Change user role to HOD | User ID | Updated User | Role consistency |
| **P2.12** | Clear Caches | Invalidate related cached data | Cache Keys | Clean Cache | Performance optimization |
| **P2.13** | Log Assignment | Record assignment in audit logs | Assignment Data | Log Entry | Compliance requirement |

### Data Flow Details

| Data Flow ID | Source Process | Destination Process | Data Elements | Security Controls | Error Handling |
|--------------|----------------|---------------------|---------------|-------------------|---------------|
| **DF2.1** | Admin User | P2.1 Select Department | Department ID | Input validation | Invalid ID error |
| **DF2.2** | P2.1 | P2.2 Validate Department | Department Record | Existence check | Department not found |
| **DF2.3** | P2.2 | P2.3 Load Lecturers | Validation Status | Access control | Permission denied |
| **DF2.4** | P2.3 | P2.4 Filter Lecturers | Lecturer List | Data filtering | Empty result set |
| **DF2.5** | Admin User | P2.5 Select Lecturer | Lecturer ID | Input validation | Invalid lecturer ID |
| **DF2.6** | P2.5 | P2.6 Validate Lecturer | Lecturer Record | Eligibility check | Inactive lecturer |
| **DF2.7** | P2.6 | P2.7 Check Conflicts | Assignment Data | Conflict detection | Assignment conflict |
| **DF2.8** | P2.7 | P2.8 Handle Reassignment | Conflict Resolution | Transaction safety | Rollback on failure |
| **DF2.9** | P2.8 | P2.9 Create Account | Account Data | Secure generation | Username conflict |
| **DF2.10** | P2.9 | P2.10 Update Department | Assignment Data | Transaction integrity | Database error |
| **DF2.11** | P2.10 | P2.11 Update Role | User Data | Role validation | Role update failure |
| **DF2.12** | P2.11 | P2.12 Clear Caches | Cache Keys | Cache management | Cache clear failure |
| **DF2.13** | P2.12 | P2.13 Log Assignment | Audit Data | Secure logging | Logging failure |

## Level 3 DFD - API Request Processing

| Process ID | Process Name | Description | Input Data | Output Data | Security Level |
|------------|--------------|-------------|------------|-------------|---------------|
| **P3.1** | Rate Limiting Check | Prevent API abuse | Request Metadata | Rate Check Result | High |
| **P3.2** | CSRF Token Validation | Prevent cross-site request forgery | CSRF Token | Validation Result | High |
| **P3.3** | Input Validation | Sanitize and validate inputs | Raw Input Data | Sanitized Data | High |
| **P3.4** | Cache Check | Check for cached data | Cache Key | Cache Hit/Miss | Medium |
| **P3.5** | Database Query | Execute database operations | Query Parameters | Raw Results | Medium |
| **P3.6** | Process Results | Format and structure data | Raw Results | Processed Data | Low |
| **P3.7** | Data Integrity Check | Validate data consistency | Processed Data | Integrity Report | Medium |
| **P3.8** | Cache Results | Store data in cache | Processed Data | Cache Entry | Low |
| **P3.9** | Log API Request | Record request details | Request Data | Log Entry | High |

### Security Control Matrix

| Security Control | Implementation | Effectiveness | Monitoring |
|------------------|----------------|----------------|------------|
| **Rate Limiting** | Token bucket algorithm, per-user limits | High | Request counts, block events |
| **CSRF Protection** | Double-submit cookie pattern, token validation | High | Invalid token attempts |
| **Input Validation** | Server-side validation, type checking, sanitization | High | Validation failures |
| **Authentication** | Session-based, role validation | High | Failed login attempts |
| **Authorization** | Admin-only access control | High | Unauthorized access attempts |
| **Data Encryption** | Password hashing, secure tokens | High | Encryption status |
| **Audit Logging** | Comprehensive activity logging | High | Log integrity, review compliance |
| **Cache Security** | Secure cache keys, TTL management | Medium | Cache hit rates, invalidation events |

### API Endpoint Specifications

| Endpoint | Method | Purpose | Rate Limit | Authentication | Authorization |
|----------|--------|---------|------------|----------------|---------------|
| `/api/assign-hod-api.php?action=get_departments` | GET | Retrieve department list | 60/min | Session | Admin |
| `/api/assign-hod-api.php?action=get_lecturers` | GET | Retrieve lecturer list | 60/min | Session | Admin |
| `/api/assign-hod-api.php?action=get_assignment_stats` | GET | Get assignment statistics | 30/min | Session | Admin |
| `/api/assign-hod-api.php?action=assign_hod` | POST | Assign/remove HOD | 10/min | Session + CSRF | Admin |

## Data Dictionary

### Data Stores

| Table Name | Primary Key | Description | Record Count Estimate | Update Frequency |
|------------|-------------|-------------|----------------------|------------------|
| **departments** | id (INT) | Department information and HOD assignments | 10-50 | Low |
| **lecturers** | id (INT) | Lecturer profiles and department associations | 50-200 | Medium |
| **users** | id (INT) | User accounts and authentication data | 200-1000 | Medium |

### Field Specifications

| Table | Field Name | Data Type | Constraints | Description | Validation Rules |
|-------|------------|-----------|-------------|-------------|------------------|
| departments | id | INT | PRIMARY KEY, AUTO_INCREMENT | Unique department identifier | > 0 |
| departments | name | VARCHAR(255) | NOT NULL, UNIQUE | Department name | 2-255 chars, alphanumeric |
| departments | hod_id | INT | FOREIGN KEY (lecturers.id), NULL | Current HOD lecturer ID | Must exist in lecturers table |
| departments | created_at | TIMESTAMP | DEFAULT CURRENT_TIMESTAMP | Record creation time | Auto-generated |
| departments | updated_at | TIMESTAMP | DEFAULT CURRENT_TIMESTAMP ON UPDATE | Last modification time | Auto-generated |
| lecturers | id | INT | PRIMARY KEY, AUTO_INCREMENT | Unique lecturer identifier | > 0 |
| lecturers | user_id | INT | FOREIGN KEY (users.id), UNIQUE | Associated user account | Must exist in users table |
| lecturers | department_id | INT | FOREIGN KEY (departments.id), NULL | Department assignment | Must exist in departments table |
| lecturers | created_at | TIMESTAMP | DEFAULT CURRENT_TIMESTAMP | Record creation time | Auto-generated |
| lecturers | updated_at | TIMESTAMP | DEFAULT CURRENT_TIMESTAMP ON UPDATE | Last modification time | Auto-generated |
| users | id | INT | PRIMARY KEY, AUTO_INCREMENT | Unique user identifier | > 0 |
| users | username | VARCHAR(50) | NOT NULL, UNIQUE | Login username | 3-50 chars, alphanumeric + underscore |
| users | email | VARCHAR(255) | NOT NULL, UNIQUE | Email address | Valid email format |
| users | password | VARCHAR(255) | NOT NULL | Hashed password | bcrypt hash |
| users | role | ENUM('admin','hod','lecturer','student') | NOT NULL | User role | Predefined values only |
| users | status | ENUM('active','inactive') | DEFAULT 'active' | Account status | Predefined values only |
| users | created_at | TIMESTAMP | DEFAULT CURRENT_TIMESTAMP | Record creation time | Auto-generated |
| users | updated_at | TIMESTAMP | DEFAULT CURRENT_TIMESTAMP ON UPDATE | Last modification time | Auto-generated |

### Data Flows

| Data Flow Name | Direction | Format | Required Fields | Optional Fields | Validation |
|----------------|-----------|--------|-----------------|----------------|------------|
| **Assignment Request** | Client → Server | JSON/POST | department_id, csrf_token | hod_id | Type validation, CSRF check |
| **Assignment Response** | Server → Client | JSON | status, message | details, debug_info | Schema validation |
| **Department List Request** | Client → Server | GET params | action=get_departments | ajax=1 | Action validation |
| **Department List Response** | Server → Client | JSON | status, data[], count | integrity_issues, cached | Array validation |
| **Lecturer List Request** | Client → Server | GET params | action=get_lecturers | department_id, ajax=1 | Parameter validation |
| **Lecturer List Response** | Server → Client | JSON | status, data[], count | cached | Array validation |
| **Statistics Request** | Client → Server | GET params | action=get_assignment_stats | ajax=1 | Action validation |
| **Statistics Response** | Server → Client | JSON | status, data{} | cached | Object validation |
| **Validation Error Response** | Server → Client | JSON | status=error, message | debug_info | Error format |

### API Response Specifications

| Response Type | HTTP Status | Content-Type | Required Fields | Optional Fields | Example |
|---------------|-------------|--------------|-----------------|-----------------|---------|
| **Success Response** | 200 | application/json | status, data | message, cached, count | `{"status":"success","data":[...]}` |
| **Error Response** | 400/403/500 | application/json | status, message | debug_info, error_code | `{"status":"error","message":"..."}` |
| **Rate Limit Response** | 429 | application/json | status, message, retry_after | - | `{"status":"error","message":"...","retry_after":60}` |
| **Validation Error** | 400 | application/json | status, message | debug_info | `{"status":"error","message":"Validation failed"}` |
| **Auth Error** | 401/403 | application/json | status, message, error_code | timestamp | `{"status":"error","message":"...","error_code":"..."}` |

## Trust Boundaries and Security Controls

### External Interfaces Matrix

| Interface | Protocol | Authentication | Data Protection | Monitoring |
|-----------|----------|----------------|-----------------|------------|
| **Web Browser** | HTTPS | Session cookies, CSRF tokens | TLS 1.3, secure headers | Request logging, anomaly detection |
| **Database Server** | MySQL | Database credentials, connection pooling | Parameterized queries, prepared statements | Query logging, performance monitoring |
| **Redis Cache** | Redis Protocol | Redis auth, connection limits | Encrypted connections (if configured) | Cache hit/miss ratios, memory usage |
| **Email Service** | SMTP | SMTP auth, DKIM/SPF | TLS encryption, content validation | Delivery status, bounce handling |
| **File System** | Local FS | OS permissions, PHP safe_mode | File permissions, path validation | Access logging, integrity checks |

### Security Controls Matrix

| Control Category | Control Name | Implementation | Effectiveness | Monitoring Method |
|------------------|--------------|----------------|----------------|-------------------|
| **Authentication** | Session-based Auth | PHP sessions with secure config | High | Failed login attempts, session timeouts |
| **Authorization** | Role-based Access | Admin-only functions, permission checks | High | Unauthorized access attempts |
| **Input Validation** | Server-side Validation | InputValidator class, type checking | High | Validation failure rates |
| **Output Sanitization** | XSS Prevention | htmlspecialchars, content filtering | High | XSS attempt detection |
| **Rate Limiting** | API Throttling | Token bucket algorithm per user | High | Request rate monitoring |
| **Audit Logging** | Activity Logging | Logger class, comprehensive events | High | Log review, compliance auditing |
| **Data Encryption** | Password Hashing | bcrypt with cost factor | High | Hash strength verification |
| **CSRF Protection** | Token Validation | Double-submit cookie pattern | High | Invalid token detection |
| **Session Security** | Secure Sessions | HttpOnly, Secure, SameSite flags | High | Session hijacking attempts |
| **Error Handling** | Secure Error Messages | Generic messages in production | Medium | Error log analysis |

## Performance Considerations

### Caching Strategy Matrix

| Cache Type | TTL Duration | Invalidation Trigger | Hit Rate Target | Fallback Method |
|------------|--------------|---------------------|-----------------|-----------------|
| **Departments List** | 10 minutes | Assignment changes, manual refresh | 85% | Database query |
| **Lecturers List** | 5 minutes | User role changes, department updates | 80% | Database query |
| **Assignment Statistics** | 5 minutes | Any assignment operation | 90% | Real-time calculation |
| **User Sessions** | 30 minutes | Logout, role changes | 95% | Database validation |
| **API Responses** | 1-5 minutes | Data modifications | 75% | Live processing |

### Database Optimization Matrix

| Optimization Type | Implementation | Benefit | Monitoring |
|-------------------|----------------|---------|------------|
| **Indexed Queries** | Primary/foreign key indexes | 90% query time reduction | Query execution plans |
| **Connection Pooling** | PDO persistent connections | 50% connection overhead reduction | Connection pool metrics |
| **Query Optimization** | SELECT field limiting, JOIN optimization | 60% data transfer reduction | Query profiling |
| **Batch Operations** | Transaction grouping, bulk inserts | 80% operation time reduction | Transaction logs |
| **Prepared Statements** | Parameterized queries | 100% SQL injection prevention | Query logs |

### Performance Monitoring Dashboard

| Metric Category | Metric Name | Target Value | Current Alert Threshold | Escalation |
|----------------|-------------|--------------|-------------------------|------------|
| **Response Times** | API Response Time | < 500ms | > 2000ms | Page load warnings |
| **Cache Performance** | Cache Hit Rate | > 80% | < 60% | Cache warming alerts |
| **Error Rates** | Application Errors | < 1% | > 5% | Immediate notification |
| **Database Performance** | Query Execution Time | < 100ms | > 500ms | Query optimization |
| **System Resources** | Memory Usage | < 80% | > 90% | Resource alerts |
| **Concurrent Users** | Active Sessions | < 100 | > 200 | Load balancing |
| **API Throughput** | Requests per Minute | < 1000 | > 2000 | Rate limiting |

## Error Handling and Recovery Matrix

| Error Category | Detection Method | User Response | System Response | Recovery Action | Logging Level |
|----------------|------------------|----------------|-----------------|-----------------|---------------|
| **Validation Errors** | InputValidator class | Show field-specific messages | Return 400 with details | User corrects input | INFO |
| **Authentication Errors** | Session validation | Redirect to login | Return 401/403 | Clear invalid sessions | WARNING |
| **Authorization Errors** | Role/permission checks | Show access denied | Return 403 | Log security event | WARNING |
| **Database Errors** | PDO exceptions | Show generic error | Return 500 | Rollback transactions | ERROR |
| **Network Errors** | cURL timeouts | Show retry message | Return 500 | Implement retry logic | WARNING |
| **Rate Limit Errors** | SecurityUtils check | Show wait message | Return 429 | Exponential backoff | INFO |
| **CSRF Errors** | Token validation | Show security error | Return 403 | Regenerate tokens | WARNING |
| **Cache Errors** | Redis/file fallback | Continue with DB | Fallback to direct queries | Monitor cache health | WARNING |
| **File System Errors** | Permission checks | Show upload error | Return 500 | Check file permissions | ERROR |

### Error Response Format Standards

| Error Type | HTTP Status | Response Structure | User Message | Debug Info |
|------------|-------------|-------------------|--------------|------------|
| **Validation** | 400 | `{"status":"error","message":"Field X is required"}` | Specific field error | Field details |
| **Authentication** | 401 | `{"status":"error","message":"Please login","error_code":"AUTH_REQUIRED"}` | Login required | Session status |
| **Authorization** | 403 | `{"status":"error","message":"Access denied","error_code":"INSUFFICIENT_PERMISSIONS"}` | Permission denied | Required roles |
| **Rate Limiting** | 429 | `{"status":"error","message":"Too many requests","retry_after":60}` | Wait and retry | Rate limit details |
| **Server Error** | 500 | `{"status":"error","message":"Internal server error","error_code":"GENERAL_ERROR"}` | Try again later | Stack trace (dev only) |
| **CSRF** | 403 | `{"status":"error","message":"Security token invalid"}` | Refresh page | Token validation |

### System Health Monitoring

| Component | Health Check | Alert Threshold | Recovery Action | Escalation |
|-----------|--------------|-----------------|-----------------|------------|
| **Database** | Connection test, query performance | > 5s response time | Connection pool reset | DBA notification |
| **Redis Cache** | Ping, memory usage | < 10% hit rate | Fallback to file cache | Cache admin alert |
| **File System** | Permission checks, disk space | < 10% free space | Cleanup old logs | System admin alert |
| **API Endpoints** | Response time monitoring | > 2s average | Load balancer adjustment | DevOps alert |
| **Session Store** | Session cleanup, count monitoring | > 1000 active sessions | Session garbage collection | Security review |
| **Log Files** | Size monitoring, rotation | > 1GB per day | Log rotation | Log analysis |

---

## Summary

This comprehensive Data Flow Diagrams documentation in tabular format provides:

### **Architectural Overview**
- **Context Level**: System boundaries and external entities
- **Level 1**: Main system processes and data flows
- **Level 2**: Detailed HOD assignment workflow
- **Level 3**: API request processing security layers

### **Technical Specifications**
- **Data Dictionary**: Complete table schemas and field specifications
- **API Specifications**: Endpoint details, rate limits, and response formats
- **Security Controls**: Authentication, authorization, and protection mechanisms
- **Performance Metrics**: Caching strategies, monitoring thresholds, and optimization targets

### **Operational Guidelines**
- **Error Handling**: Classification, response formats, and recovery procedures
- **Monitoring**: Health checks, alert thresholds, and escalation procedures
- **Compliance**: Audit logging, data integrity, and security controls

This documentation serves as a complete reference for system architects, developers, security auditors, and system administrators working with the HOD Assignment System.