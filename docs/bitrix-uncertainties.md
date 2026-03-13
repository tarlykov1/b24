# Known Bitrix-specific uncertainties

1. Exact API/DB support for forcing source IDs per entity type (tasks/CRM/smart processes/chats).
2. Whether comment creation APIs allow preserving historical author + created datetime without elevated internals.
3. CRM pipeline/stage migration behavior across differing target metadata IDs.
4. Smart process schema portability between portals with partially overlapping type definitions.
5. Business process and robot template export/import compatibility across Bitrix versions.
6. Safe import path for automations in disabled state for all supported modules.
7. Chat/message migration constraints (IM module internals, attachment mapping, service messages).
8. Calendar recurring event and attendee state fidelity during migration.
9. Filesystem + Disk versioning semantics and linked object permissions recreation.
10. Group/project role mapping edge cases for archived/extranet/private groups.
11. Limitations of webhook/REST vs direct DB + module APIs for large-volume extraction.
12. Eventual consistency delays in Bitrix indexes/search affecting immediate verification checks.
13. Keycloak provisioning race conditions that may create users before migration mapping applies.
14. Support boundaries for migrating fired/blocked users while preserving ownership semantics.
15. Module-specific throttling safe limits in production under concurrent portal load.
