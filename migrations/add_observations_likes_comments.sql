-- Create observation_likes table
CREATE TABLE IF NOT EXISTS observation_likes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    observation_id INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (observation_id) REFERENCES observations(id) ON DELETE CASCADE,
    UNIQUE KEY unique_like (user_id, observation_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Create observation_comments table
CREATE TABLE IF NOT EXISTS observation_comments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    observation_id INT NOT NULL,
    comment_text TEXT NOT NULL,
    parent_comment_id INT DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (observation_id) REFERENCES observations(id) ON DELETE CASCADE,
    FOREIGN KEY (parent_comment_id) REFERENCES observation_comments(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Add indexes for better performance
CREATE INDEX idx_observation_likes_obs ON observation_likes(observation_id);
CREATE INDEX idx_observation_comments_obs ON observation_comments(observation_id);

