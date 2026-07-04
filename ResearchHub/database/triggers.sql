-- =====================================================
-- RESEARCHHUB TRIGGERS
-- =====================================================

-- ==========================================
-- Trigger 1
-- Audit Paper Insert
-- ==========================================

CREATE OR REPLACE TRIGGER TRG_PAPER_INSERT
AFTER INSERT ON PAPERS
FOR EACH ROW
BEGIN

    INSERT INTO AUDIT_LOGS(
        LOG_ID,
        USER_ID,
        ACTION_TYPE,
        TABLE_NAME,
        ACTION_DATE,
        DESCRIPTION
    )
    VALUES(
        (SELECT NVL(MAX(LOG_ID),0)+1 FROM AUDIT_LOGS),
        :NEW.RESEARCHER_ID,
        'INSERT',
        'PAPERS',
        SYSDATE,
        'New paper submitted'
    );

END;
/

-- ==========================================
-- Trigger 2
-- Audit Paper Update
-- ==========================================

CREATE OR REPLACE TRIGGER TRG_PAPER_UPDATE
AFTER UPDATE ON PAPERS
FOR EACH ROW
BEGIN

    INSERT INTO AUDIT_LOGS(
        LOG_ID,
        USER_ID,
        ACTION_TYPE,
        TABLE_NAME,
        ACTION_DATE,
        DESCRIPTION
    )
    VALUES(
        (SELECT NVL(MAX(LOG_ID),0)+1 FROM AUDIT_LOGS),
        :NEW.RESEARCHER_ID,
        'UPDATE',
        'PAPERS',
        SYSDATE,
        'Paper updated'
    );

END;
/

-- ==========================================
-- Trigger 3
-- Audit Thesis Insert
-- ==========================================

CREATE OR REPLACE TRIGGER TRG_THESIS_INSERT
AFTER INSERT ON THESES
FOR EACH ROW
BEGIN

    INSERT INTO AUDIT_LOGS(
        LOG_ID,
        USER_ID,
        ACTION_TYPE,
        TABLE_NAME,
        ACTION_DATE,
        DESCRIPTION
    )
    VALUES(
        (SELECT NVL(MAX(LOG_ID),0)+1 FROM AUDIT_LOGS),
        :NEW.RESEARCHER_ID,
        'INSERT',
        'THESES',
        SYSDATE,
        'New thesis submitted'
    );

END;
/

-- ==========================================
-- Trigger 4
-- Automatically increase thesis version
-- ==========================================

CREATE OR REPLACE TRIGGER TRG_THESIS_VERSION
BEFORE UPDATE ON THESES
FOR EACH ROW
BEGIN

    :NEW.VERSION_NO := :OLD.VERSION_NO + 1;

END;
/

-- ==========================================
-- Trigger 5
-- Default publication year
-- ==========================================

CREATE OR REPLACE TRIGGER TRG_PAPER_YEAR
BEFORE INSERT ON PAPERS
FOR EACH ROW
BEGIN

    IF :NEW.PUBLICATION_YEAR IS NULL THEN
        :NEW.PUBLICATION_YEAR :=
            EXTRACT(YEAR FROM SYSDATE);
    END IF;

END;
/
