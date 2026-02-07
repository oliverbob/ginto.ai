-- User documents table for RAG (Retrieval-Augmented Generation)
-- Stores uploaded documents and extracted text for AI context

CREATE TABLE IF NOT EXISTS user_documents (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED NOT NULL,
    filename VARCHAR(255) NOT NULL COMMENT 'Original filename',
    stored_filename VARCHAR(255) NOT NULL COMMENT 'Unique stored filename',
    file_path VARCHAR(500) NOT NULL COMMENT 'Full path to stored file',
    mime_type VARCHAR(100) NOT NULL COMMENT 'File MIME type',
    doc_type VARCHAR(20) NOT NULL COMMENT 'Document type: pdf, txt, md, doc, docx, rtf, html',
    file_size INT UNSIGNED NOT NULL COMMENT 'File size in bytes',
    extracted_text LONGTEXT COMMENT 'Extracted text content for RAG',
    text_length INT UNSIGNED DEFAULT 0 COMMENT 'Length of extracted text',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    INDEX idx_user_id (user_id),
    INDEX idx_created_at (created_at),
    INDEX idx_doc_type (doc_type),
    
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
