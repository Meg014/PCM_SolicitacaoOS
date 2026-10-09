# Modelo de dados inicial

## users
- id
- name
- username/email
- password_hash
- role
- active
- created
- modified

## sectors
- id
- code nullable
- name
- active
- created
- modified

## service_requests
- id
- request_number UNIQUE
- requester_id FK users
- sector_id FK sectors
- asset_code
- asset_description
- maintenance_type
- description
- priority nullable
- status
- assigned_to nullable FK users
- assigned_at nullable
- totvs_os_number nullable
- totvs_os_linked_by nullable FK users
- totvs_os_linked_at nullable
- cancelled_by nullable FK users
- cancelled_at nullable
- cancellation_reason nullable
- created
- modified

Índices recomendados:
- request_number
- requester_id
- sector_id
- status
- asset_code
- created
- totvs_os_number

## service_request_items
- id
- service_request_id FK
- description
- sort_order
- created
- modified

## request_attachments
- id
- service_request_id FK
- original_name
- stored_name
- mime_type
- size_bytes
- uploaded_by FK users
- created

## request_audit_logs
- id
- service_request_id FK
- user_id nullable FK users
- action
- old_values JSONB nullable
- new_values JSONB nullable
- metadata JSONB nullable
- created

## Observação
Não armazenar senha em texto. Não excluir auditoria em cascata sem pensar no requisito histórico.
