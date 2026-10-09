-- Referência inicial. No CakePHP, converter para migrations.

CREATE TABLE sectors (
    id BIGSERIAL PRIMARY KEY,
    code VARCHAR(30),
    name VARCHAR(120) NOT NULL,
    active BOOLEAN NOT NULL DEFAULT TRUE,
    created TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    modified TIMESTAMPTZ NOT NULL DEFAULT NOW()
);

CREATE TABLE service_requests (
    id BIGSERIAL PRIMARY KEY,
    request_number VARCHAR(30) NOT NULL UNIQUE,
    requester_id BIGINT NOT NULL REFERENCES users(id),
    sector_id BIGINT NOT NULL REFERENCES sectors(id),
    asset_code VARCHAR(80) NOT NULL,
    asset_description VARCHAR(255) NOT NULL,
    maintenance_type VARCHAR(40) NOT NULL,
    description TEXT NOT NULL,
    priority VARCHAR(20),
    status VARCHAR(40) NOT NULL DEFAULT 'AGUARDANDO_PCM',
    assigned_to BIGINT REFERENCES users(id),
    assigned_at TIMESTAMPTZ,
    totvs_os_number VARCHAR(80),
    totvs_os_linked_by BIGINT REFERENCES users(id),
    totvs_os_linked_at TIMESTAMPTZ,
    cancelled_by BIGINT REFERENCES users(id),
    cancelled_at TIMESTAMPTZ,
    cancellation_reason TEXT,
    created TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    modified TIMESTAMPTZ NOT NULL DEFAULT NOW()
);

CREATE INDEX idx_service_requests_requester ON service_requests(requester_id);
CREATE INDEX idx_service_requests_sector ON service_requests(sector_id);
CREATE INDEX idx_service_requests_status ON service_requests(status);
CREATE INDEX idx_service_requests_asset_code ON service_requests(asset_code);
CREATE INDEX idx_service_requests_created ON service_requests(created);
CREATE INDEX idx_service_requests_totvs_os ON service_requests(totvs_os_number);

CREATE TABLE service_request_items (
    id BIGSERIAL PRIMARY KEY,
    service_request_id BIGINT NOT NULL REFERENCES service_requests(id) ON DELETE CASCADE,
    description TEXT NOT NULL,
    sort_order INTEGER NOT NULL DEFAULT 0,
    created TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    modified TIMESTAMPTZ NOT NULL DEFAULT NOW()
);

CREATE TABLE request_attachments (
    id BIGSERIAL PRIMARY KEY,
    service_request_id BIGINT NOT NULL REFERENCES service_requests(id) ON DELETE CASCADE,
    original_name VARCHAR(255) NOT NULL,
    stored_name VARCHAR(255) NOT NULL,
    mime_type VARCHAR(120) NOT NULL,
    size_bytes BIGINT NOT NULL,
    uploaded_by BIGINT NOT NULL REFERENCES users(id),
    created TIMESTAMPTZ NOT NULL DEFAULT NOW()
);

CREATE TABLE request_audit_logs (
    id BIGSERIAL PRIMARY KEY,
    service_request_id BIGINT NOT NULL REFERENCES service_requests(id),
    user_id BIGINT REFERENCES users(id),
    action VARCHAR(80) NOT NULL,
    old_values JSONB,
    new_values JSONB,
    metadata JSONB,
    created TIMESTAMPTZ NOT NULL DEFAULT NOW()
);

CREATE INDEX idx_request_audit_request ON request_audit_logs(service_request_id);
CREATE INDEX idx_request_audit_created ON request_audit_logs(created);
