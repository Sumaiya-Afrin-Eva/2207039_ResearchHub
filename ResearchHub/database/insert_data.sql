-- ==========================================
-- SAMPLE DATA FOR RESEARCHHUB
-- ==========================================

-- ROLES

INSERT INTO ROLE VALUES (1,'ADMIN');
INSERT INTO ROLE VALUES (2,'RESEARCHER');
INSERT INTO ROLE VALUES (3,'SUPERVISOR');
INSERT INTO ROLE VALUES (4,'REVIEWER');

-- DEPARTMENTS

INSERT INTO DEPARTMENTS VALUES (1,'Computer Science & Engineering','Engineering');
INSERT INTO DEPARTMENTS VALUES (2,'Electrical & Electronic Engineering','Engineering');
INSERT INTO DEPARTMENTS VALUES (3,'Business Administration','Business');

-- USERS

INSERT INTO USERS VALUES
(1,1,1,'System','Admin',
'admin@researchhub.com',
'admin123',
'University',
SYSDATE,
'ACTIVE');

INSERT INTO USERS VALUES
(2,2,1,'Sumaiya','Afrin Eva',
'sumaiya@gmail.com',
'pass123',
'Daffodil International University',
SYSDATE,
'ACTIVE');

INSERT INTO USERS VALUES
(3,2,1,'John','Smith',
'john@gmail.com',
'pass123',
'DIU',
SYSDATE,
'ACTIVE');

INSERT INTO USERS VALUES
(4,3,1,'Dr.','Hasan',
'hasan@gmail.com',
'pass123',
'DIU',
SYSDATE,
'ACTIVE');

INSERT INTO USERS VALUES
(5,3,2,'Dr.','Rahman',
'rahman@gmail.com',
'pass123',
'DIU',
SYSDATE,
'ACTIVE');

INSERT INTO USERS VALUES
(6,4,1,'Dr.','Karim',
'karim@gmail.com',
'pass123',
'DIU',
SYSDATE,
'ACTIVE');

INSERT INTO USERS VALUES
(7,4,2,'Dr.','Akter',
'akter@gmail.com',
'pass123',
'DIU',
SYSDATE,
'ACTIVE');

-- PAPERS

INSERT INTO PAPERS VALUES
(
1,
2,
'AI Based Disease Detection',
'Research on AI for disease detection',
'AI,Machine Learning,Healthcare',
SYSDATE,
2026,
'SUBMITTED'
);

INSERT INTO PAPERS VALUES
(
2,
3,
'Blockchain Security Framework',
'Research on blockchain security',
'Blockchain,Cyber Security',
SYSDATE,
2026,
'UNDER REVIEW'
);

-- PAPER AUTHORS

INSERT INTO PAPER_AUTHORS VALUES
(
1,
1,
'Sumaiya Afrin Eva',
'sumaiya@gmail.com',
'DIU'
);

INSERT INTO PAPER_AUTHORS VALUES
(
2,
2,
'John Smith',
'john@gmail.com',
'DIU'
);

-- REVIEW ASSIGNMENTS

INSERT INTO REVIEW_ASSIGNMENTS VALUES
(
1,
1,
6,
SYSDATE,
'PENDING'
);

INSERT INTO REVIEW_ASSIGNMENTS VALUES
(
2,
2,
7,
SYSDATE,
'COMPLETED'
);

-- REVIEWS

INSERT INTO REVIEWS VALUES
(
1,
2,
9,
'Excellent work with minor revisions',
SYSDATE,
'MINOR REVISION'
);

-- THESES

INSERT INTO THESES VALUES
(
1,
2,
1,
'AI Driven Medical Diagnosis',
'Thesis on AI diagnosis systems',
SYSDATE,
1,
'SUBMITTED'
);

INSERT INTO THESES VALUES
(
2,
3,
1,
'Blockchain in Healthcare',
'Healthcare blockchain thesis',
SYSDATE,
1,
'UNDER REVIEW'
);

-- THESIS SUPERVISIONS

INSERT INTO THESIS_SUPERVISIONS VALUES
(
1,
1,
4,
'PRIMARY'
);

INSERT INTO THESIS_SUPERVISIONS VALUES
(
2,
2,
5,
'PRIMARY'
);

-- AUDIT LOGS

INSERT INTO AUDIT_LOGS VALUES
(
1,
1,
'INSERT',
'USERS',
SYSDATE,
'Created researcher account'
);

INSERT INTO AUDIT_LOGS VALUES
(
2,
1,
'UPDATE',
'PAPERS',
SYSDATE,
'Updated paper status'
);

COMMIT;