-- Shto kolonën 'message' në tabelën 'messages' nëse nuk ekziston
ALTER TABLE messages ADD COLUMN message TEXT NOT NULL AFTER receiver_id;