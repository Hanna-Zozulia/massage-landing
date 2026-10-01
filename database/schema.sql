CREATE TABLE leads (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    service VARCHAR(100) NOT NULL,
    preferred_date DATE NOT NULL,
    email VARCHAR(254) NOT NULL,
    name VARCHAR(150) NOT NULL,
    phone VARCHAR(50) NOT NULL,
    contact_method VARCHAR(30) NOT NULL,
    status ENUM('new', 'read', 'contacted', 'closed') NOT NULL DEFAULT 'new',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,

    PRIMARY KEY (id),
    INDEX idx_leads_status (status),
    INDEX idx_leads_created_at (created_at),
    INDEX idx_leads_preferred_date (preferred_date)
) ENGINE=InnoDB DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

CREATE TABLE visits (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    visited_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    visitor_token CHAR(64) NOT NULL,
    user_agent VARCHAR(512) NULL,

    PRIMARY KEY (id),
    INDEX idx_visits_visited_at (visited_at),
    INDEX idx_visits_visitor_token (visitor_token)
) ENGINE=InnoDB DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;