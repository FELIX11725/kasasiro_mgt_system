-- Add response fields to CustomerQueries table
ALTER TABLE CustomerQueries
ADD COLUMN response TEXT NULL AFTER resolved_at,
ADD COLUMN responded_at TIMESTAMP NULL AFTER response,
ADD COLUMN response_by_user_id INT NULL AFTER responded_at,
ADD CONSTRAINT fk_response_by_user FOREIGN KEY (response_by_user_id) REFERENCES Users(user_id) ON DELETE SET NULL;

-- Add link_url field to Notifications table
ALTER TABLE Notifications
ADD COLUMN link_url VARCHAR(255) NULL AFTER message;

-- Optional: Update existing records if needed, e.g., set default for link_url if it cannot be NULL and has no default in your DB engine (though VARCHAR NULL is fine)
-- UPDATE Notifications SET link_url = '#' WHERE link_url IS NULL; -- Example, if you wanted a default non-NULL link
-- (Not strictly necessary with VARCHAR(255) NULL)
