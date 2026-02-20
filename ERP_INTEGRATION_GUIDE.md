# ERP Integration Guide

## What's New

Your chatbot now has **full ERP integration** with KL University! Users can:
- Set their ERP login credentials securely
- Ask the chatbot about their marks, attendance, and grades
- Get real data fetched directly from the ERP portal
- Receive intelligent analysis and suggestions

## How It Works

### 1. User Logs Into Chatbot
- User registers/logs into your chatbot via the web interface
- User_id is stored in their session

### 2. Set ERP Credentials
- User clicks **"Set ERP Login"** button on the chat page
- A modal appears asking for ERP username and password
- Credentials are encrypted and stored in the database
- User can update credentials anytime

### 3. Ask About Academic Details
- When user asks: "What are my marks?" or "Check my attendance"
- Chatbot automatically:
  - Logs into the KL University ERP portal using their credentials
  - Fetches their marks, attendance, or grades
  - Analyzes the data
  - Provides intelligent response with suggestions

## Files Added/Modified

### New Files:
- `ERPIntegration.php` - Core ERP connection and data fetching class
- `get_bot_message_erp.php` - Main chatbot endpoint with ERP support
- `test_groq.php` - API testing utility

### Modified Files:
- `index.php` - Now stores user_id in session
- `homepage.php` - Added "Set ERP Login" button and modal
- `dbconfig/config.php` - Added Groq API configuration
- Database schema - Added erp_username and erp_password columns to users table

## Database Schema Update

Two columns were added to the `users` table:
```sql
ALTER TABLE users ADD erp_username VARCHAR(255);
ALTER TABLE users ADD erp_password VARCHAR(500);
```

Passwords are stored as base64 encoded (not recommended for production - use proper encryption).

## Usage Examples

### Example 1: Check Marks
**User:** "What are my marks in Java?"
**Bot:** Fetches data from ERP and responds with:
- Subject-wise marks
- Overall performance analysis
- Suggestions for improvement

### Example 2: Check Attendance
**User:** "Show me my attendance"
**Bot:** Fetches and displays:
- Attendance percentage by subject
- Warning if attendance is low
- Deadline for attendance improvement

### Example 3: Guide Without Data
**User:** "How do I register for exams?"
**Bot:** Provides step-by-step guide even without ERP data

## Security Considerations

⚠️ **Current Implementation:**
- Passwords are base64 encoded (for demo purposes only)
- Credentials stored in database
- Temporary cookie files stored in system temp directory

🔒 **For Production:**
- Use AES-256 encryption instead of base64
- Consider OAuth authentication
- Use environment variables for sensitive data
- Store cookies in secure, restricted directories
- Never log passwords in error logs
- Implement HTTPS-only transmission
- Add request validation and rate limiting

## Testing

1. Log in to the chatbot at: http://localhost:8001
2. Click "Set ERP Login" button
3. Enter your KL University ERP credentials
4. Ask questions like:
   - "Show me my grades"
   - "What's my attendance percentage?"
   - "Check my marks in all subjects"

## Troubleshooting

### Issue: "Login failed. Please verify credentials."
- **Solution:** Verify your ERP username and password are correct
- Try logging directly into https://newerp.kluniversity.in/ first

### Issue: "Failed to fetch marks"
- **Solution:** ERP portal might be down or your account may not have accessible data yet
- Contact KL University IT support

### Issue: Credentials not saving
- **Solution:** Check database connection, ensure erp_username and erp_password columns exist
- Run: `DESCRIBE users;` to verify columns

## Future Enhancements

- [ ] Add more ERP features (fee details, exam schedule, notifications)
- [ ] Implement proper encryption for credentials
- [ ] Add multi-language support
- [ ] Create admin dashboard to monitor usage
- [ ] Add email notifications for low attendance
- [ ] Integrate official KL University ERP API (if available)

## Support

For issues or questions:
1. Check the error logs in browser console (F12)
2. Check PHP error logs: `/var/log/php-fpm.log` or similar
3. Verify database credentials in `dbconfig/config.php`
4. Test API: Run `php test_groq.php`

---

**Last Updated:** February 20, 2026
**Version:** 1.0 with ERP Integration
