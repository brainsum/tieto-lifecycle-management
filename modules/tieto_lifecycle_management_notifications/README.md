# Life-cycle management - Notifications

## Fallback recipient users

The module tries to load users from the entity from pre-set fields.
If none are found, fallback users are configurable via the `fallback_recipients` key 
of the module config (`tieto_lifecycle_management_notifications.settings`).
This is a simple string array.
