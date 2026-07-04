-- =====================================================
-- RESEARCHHUB STORED PROCEDURES
-- Oracle 11g XE
-- =====================================================

-- =====================================================
-- PROCEDURE 1: SUBMIT_PAPER
-- Adds a new research paper
-- =====================================================

CREATE OR REPLACE PROCEDURE SUBMIT_PAPER(
    P_PAPER_ID           IN NUMBER,
    P_RESEARCHER_ID      IN NUMBER,
    P_TITLE              IN VARCHAR2,
    P_ABSTRACT           IN CLOB,
    P_KEYWORDS           IN VARCHAR2,
    P_PUBLICATION_YEAR   IN NUMBER
)
AS
BEGIN

    INSERT INTO PAPERS (
        PAPER_ID,
        RESEARCHER_ID,
        TITLE,
        ABSTRACT,
        KEYWORDS,
        SUBMISSION_DATE,
        PUBLICATION_YEAR,
        STATUS
    )
    VALUES (
        P_PAPER_ID,
        P_RESEARCHER_ID,
        P_TITLE,
        P_ABSTRACT,
        P_KEYWORDS,
        SYSDATE,
        P_PUBLICATION_YEAR,
        'SUBMITTED'
    );

    COMMIT;

EXCEPTION
    WHEN OTHERS THEN
        ROLLBACK;
        RAISE;
END;
/
-- =====================================================
-- PROCEDURE 2: ASSIGN_REVIEWER
-- Assigns reviewer to a paper
-- =====================================================

CREATE OR REPLACE PROCEDURE ASSIGN_REVIEWER(
    P_ASSIGNMENT_ID IN NUMBER,
    P_PAPER_ID      IN NUMBER,
    P_REVIEWER_ID   IN NUMBER
)
AS
BEGIN

    INSERT INTO REVIEW_ASSIGNMENTS(
        ASSIGNMENT_ID,
        PAPER_ID,
        REVIEWER_ID,
        ASSIGNED_DATE,
        ASSIGNMENT_STATUS
    )
    VALUES(
        P_ASSIGNMENT_ID,
        P_PAPER_ID,
        P_REVIEWER_ID,
        SYSDATE,
        'PENDING'
    );

    UPDATE PAPERS
    SET STATUS = 'UNDER REVIEW'
    WHERE PAPER_ID = P_PAPER_ID;

    COMMIT;

EXCEPTION
    WHEN OTHERS THEN
        ROLLBACK;
        RAISE;
END;
/
-- =====================================================
-- PROCEDURE 3: SUBMIT_REVIEW
-- Stores review and updates assignment status
-- =====================================================

CREATE OR REPLACE PROCEDURE SUBMIT_REVIEW(
    P_REVIEW_ID        IN NUMBER,
    P_ASSIGNMENT_ID    IN NUMBER,
    P_SCORE            IN NUMBER,
    P_COMMENTS         IN CLOB,
    P_RECOMMENDATION   IN VARCHAR2
)
AS
BEGIN

    INSERT INTO REVIEWS(
        REVIEW_ID,
        ASSIGNMENT_ID,
        SCORE,
        COMMENTS,
        REVIEW_DATE,
        RECOMMENDATION
    )
    VALUES(
        P_REVIEW_ID,
        P_ASSIGNMENT_ID,
        P_SCORE,
        P_COMMENTS,
        SYSDATE,
        P_RECOMMENDATION
    );

    UPDATE REVIEW_ASSIGNMENTS
    SET ASSIGNMENT_STATUS = 'COMPLETED'
    WHERE ASSIGNMENT_ID = P_ASSIGNMENT_ID;

    COMMIT;

EXCEPTION
    WHEN OTHERS THEN
        ROLLBACK;
        RAISE;
END;
/
-- =====================================================
-- PROCEDURE 4: UPDATE_PAPER_STATUS
-- Changes paper status
-- =====================================================

CREATE OR REPLACE PROCEDURE UPDATE_PAPER_STATUS(
    P_PAPER_ID IN NUMBER,
    P_STATUS   IN VARCHAR2
)
AS
BEGIN

    UPDATE PAPERS
    SET STATUS = P_STATUS
    WHERE PAPER_ID = P_PAPER_ID;

    COMMIT;

EXCEPTION
    WHEN OTHERS THEN
        ROLLBACK;
        RAISE;
END;
/
-- =====================================================
-- PROCEDURE 5: APPROVE_THESIS
-- Approves thesis submission
-- =====================================================

CREATE OR REPLACE PROCEDURE APPROVE_THESIS(
    P_THESIS_ID IN NUMBER
)
AS
BEGIN

    UPDATE THESES
    SET STATUS = 'APPROVED'
    WHERE THESIS_ID = P_THESIS_ID;

    COMMIT;

EXCEPTION
    WHEN OTHERS THEN
        ROLLBACK;
        RAISE;
END;
/
-- =====================================================
-- PROCEDURE 6: ADD_AUDIT_LOG
-- Inserts a new audit log record
-- =====================================================

CREATE OR REPLACE PROCEDURE ADD_AUDIT_LOG(
    P_LOG_ID        IN NUMBER,
    P_USER_ID       IN NUMBER,
    P_ACTION_TYPE   IN VARCHAR2,
    P_TABLE_NAME    IN VARCHAR2,
    P_DESCRIPTION   IN VARCHAR2
)
AS
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
        P_LOG_ID,
        P_USER_ID,
        P_ACTION_TYPE,
        P_TABLE_NAME,
        SYSDATE,
        P_DESCRIPTION
    );

    COMMIT;

EXCEPTION
    WHEN OTHERS THEN
        ROLLBACK;
        RAISE;
END;
/
-- =====================================================
-- TEST EXECUTION EXAMPLES
-- Uncomment and run individually if needed
-- =====================================================

/*
BEGIN
    SUBMIT_PAPER(
        3,
        2,
        'Cloud Security Framework',
        'Research on Cloud Security',
        'Cloud,Security',
        2026
    );
END;
/
*/

/*
BEGIN
    ASSIGN_REVIEWER(
        3,
        1,
        7
    );
END;
/
*/

/*
BEGIN
    SUBMIT_REVIEW(
        2,
        1,
        8,
        'Good work with minor revisions',
        'MINOR REVISION'
    );
END;
/
*/

/*
BEGIN
    UPDATE_PAPER_STATUS(
        1,
        'PUBLISHED'
    );
END;
/
*/

/*
BEGIN
    APPROVE_THESIS(
        1
    );
END;
/
*/

/*
BEGIN
    ADD_AUDIT_LOG(
        3,
        1,
        'INSERT',
        'PAPERS',
        'New paper submitted'
    );
END;
/
*/

-- =====================================================
-- VERIFY PROCEDURES
-- =====================================================

/*
SELECT OBJECT_NAME
FROM USER_OBJECTS
WHERE OBJECT_TYPE = 'PROCEDURE'
ORDER BY OBJECT_NAME;
*/
-- =====================================================
-- PROCEDURE: DELETE_USER
-- Permanently deletes a user and all dependent records.
-- =====================================================

CREATE OR REPLACE PROCEDURE DELETE_USER(
    P_USER_ID   IN NUMBER,
    P_ADMIN_ID  IN NUMBER
)
AS
    V_LOG_ID    NUMBER;
    V_EMAIL     VARCHAR2(100);
    V_DESC      VARCHAR2(500);
BEGIN
    -- Fetch user email for audit entry
    SELECT EMAIL INTO V_EMAIL
    FROM   USERS
    WHERE  USER_ID = P_USER_ID;

    -- Write audit log BEFORE deleting (admin is the actor)
    SELECT COALESCE(MAX(LOG_ID), 0) + 1
    INTO   V_LOG_ID
    FROM   AUDIT_LOGS;

    V_DESC := 'Admin permanently deleted user ID: '
              || TO_CHAR(P_USER_ID) || ' (' || V_EMAIL || ')';

    INSERT INTO AUDIT_LOGS (
        LOG_ID, USER_ID, ACTION_TYPE, TABLE_NAME, ACTION_DATE, DESCRIPTION
    ) VALUES (
        V_LOG_ID, P_ADMIN_ID, 'DELETE', 'USERS', SYSDATE, V_DESC
    );

    -- 1. Delete REVIEWS linked to this user's reviewer assignments
    DELETE FROM REVIEWS
    WHERE  ASSIGNMENT_ID IN (
        SELECT ASSIGNMENT_ID FROM REVIEW_ASSIGNMENTS
        WHERE  REVIEWER_ID = P_USER_ID
    );

    -- 2. Delete REVIEWS linked to papers by this researcher
    DELETE FROM REVIEWS
    WHERE  ASSIGNMENT_ID IN (
        SELECT RA.ASSIGNMENT_ID
        FROM   REVIEW_ASSIGNMENTS RA
        JOIN   PAPERS P ON RA.PAPER_ID = P.PAPER_ID
        WHERE  P.RESEARCHER_ID = P_USER_ID
    );

    -- 3. Delete REVIEW_ASSIGNMENTS where user is reviewer
    DELETE FROM REVIEW_ASSIGNMENTS WHERE REVIEWER_ID = P_USER_ID;

    -- 4. Delete REVIEW_ASSIGNMENTS for this user's papers
    DELETE FROM REVIEW_ASSIGNMENTS
    WHERE  PAPER_ID IN (SELECT PAPER_ID FROM PAPERS WHERE RESEARCHER_ID = P_USER_ID);

    -- 5. Delete PAPER_AUTHORS for this user's papers
    DELETE FROM PAPER_AUTHORS
    WHERE  PAPER_ID IN (SELECT PAPER_ID FROM PAPERS WHERE RESEARCHER_ID = P_USER_ID);

    -- 6. Delete PAPERS by this researcher
    DELETE FROM PAPERS WHERE RESEARCHER_ID = P_USER_ID;

    -- 7. Delete THESIS_SUPERVISIONS where user is supervisor
    DELETE FROM THESIS_SUPERVISIONS WHERE SUPERVISOR_ID = P_USER_ID;

    -- 8. Delete THESIS_SUPERVISIONS for theses by this researcher
    DELETE FROM THESIS_SUPERVISIONS
    WHERE  THESIS_ID IN (SELECT THESIS_ID FROM THESES WHERE RESEARCHER_ID = P_USER_ID);

    -- 9. Delete THESES by this researcher
    DELETE FROM THESES WHERE RESEARCHER_ID = P_USER_ID;

    -- 10. Delete this user's own AUDIT_LOGS (preserve the admin entry we just created)
    DELETE FROM AUDIT_LOGS
    WHERE  USER_ID = P_USER_ID AND LOG_ID != V_LOG_ID;

    -- 11. Finally delete the user record
    DELETE FROM USERS WHERE USER_ID = P_USER_ID;

    COMMIT;

EXCEPTION
    WHEN OTHERS THEN
        ROLLBACK;
        RAISE;
END;
/

-- =====================================================
-- PROCEDURE: ADD_DEPARTMENT
-- Inserts a new department and logs the audit entry.
-- =====================================================

CREATE OR REPLACE PROCEDURE ADD_DEPARTMENT(
    P_DEPT_NAME IN VARCHAR2,
    P_FACULTY   IN VARCHAR2,
    P_ADMIN_ID  IN NUMBER
)
AS
    V_DEPT_ID NUMBER;
    V_LOG_ID  NUMBER;
BEGIN
    SELECT COALESCE(MAX(DEPARTMENT_ID), 0) + 1 INTO V_DEPT_ID FROM DEPARTMENTS;
    
    INSERT INTO DEPARTMENTS (DEPARTMENT_ID, DEPARTMENT_NAME, FACULTY)
    VALUES (V_DEPT_ID, P_DEPT_NAME, P_FACULTY);

    -- Log action
    SELECT COALESCE(MAX(LOG_ID), 0) + 1 INTO V_LOG_ID FROM AUDIT_LOGS;
    INSERT INTO AUDIT_LOGS (LOG_ID, USER_ID, ACTION_TYPE, TABLE_NAME, ACTION_DATE, DESCRIPTION)
    VALUES (V_LOG_ID, P_ADMIN_ID, 'INSERT', 'DEPARTMENTS', SYSDATE, 'Added new department: ' || P_DEPT_NAME);

    COMMIT;
EXCEPTION
    WHEN OTHERS THEN
        ROLLBACK;
        RAISE;
END;
/

-- =====================================================
-- PROCEDURE: UPDATE_DEPARTMENT
-- Updates an existing department and logs the audit entry.
-- =====================================================

CREATE OR REPLACE PROCEDURE UPDATE_DEPARTMENT(
    P_DEPT_ID   IN NUMBER,
    P_DEPT_NAME IN VARCHAR2,
    P_FACULTY   IN VARCHAR2,
    P_ADMIN_ID  IN NUMBER
)
AS
    V_LOG_ID NUMBER;
BEGIN
    UPDATE DEPARTMENTS
    SET DEPARTMENT_NAME = P_DEPT_NAME,
        FACULTY = P_FACULTY
    WHERE DEPARTMENT_ID = P_DEPT_ID;

    -- Log action
    SELECT COALESCE(MAX(LOG_ID), 0) + 1 INTO V_LOG_ID FROM AUDIT_LOGS;
    INSERT INTO AUDIT_LOGS (LOG_ID, USER_ID, ACTION_TYPE, TABLE_NAME, ACTION_DATE, DESCRIPTION)
    VALUES (V_LOG_ID, P_ADMIN_ID, 'UPDATE', 'DEPARTMENTS', SYSDATE, 'Updated department ID ' || P_DEPT_ID || ': ' || P_DEPT_NAME);

    COMMIT;
EXCEPTION
    WHEN OTHERS THEN
        ROLLBACK;
        RAISE;
END;
/

-- =====================================================
-- PROCEDURE: DELETE_DEPARTMENT
-- Deletes a department, cascading deletion to all users
-- and theses in the department, and logging the audit.
-- =====================================================

CREATE OR REPLACE PROCEDURE DELETE_DEPARTMENT(
    P_DEPT_ID  IN NUMBER,
    P_ADMIN_ID IN NUMBER
)
AS
    V_LOG_ID NUMBER;
BEGIN
    -- 1. Cascade delete all users belonging to this department using DELETE_USER procedure
    FOR r_user IN (SELECT USER_ID FROM USERS WHERE DEPARTMENT_ID = P_DEPT_ID) LOOP
        DELETE_USER(P_USER_ID => r_user.USER_ID, P_ADMIN_ID => P_ADMIN_ID);
    END LOOP;

    -- 2. Delete any remaining theses directly linked to this department
    FOR r_thesis IN (SELECT THESIS_ID FROM THESES WHERE DEPARTMENT_ID = P_DEPT_ID) LOOP
        DELETE FROM THESIS_SUPERVISIONS WHERE THESIS_ID = r_thesis.THESIS_ID;
        DELETE FROM THESES WHERE THESIS_ID = r_thesis.THESIS_ID;
    END LOOP;

    -- 3. Log action
    SELECT COALESCE(MAX(LOG_ID), 0) + 1 INTO V_LOG_ID FROM AUDIT_LOGS;
    INSERT INTO AUDIT_LOGS (LOG_ID, USER_ID, ACTION_TYPE, TABLE_NAME, ACTION_DATE, DESCRIPTION)
    VALUES (V_LOG_ID, P_ADMIN_ID, 'DELETE', 'DEPARTMENTS', SYSDATE, 'Deleted department ID ' || P_DEPT_ID);

    -- 4. Delete the department record itself
    DELETE FROM DEPARTMENTS WHERE DEPARTMENT_ID = P_DEPT_ID;

    COMMIT;
EXCEPTION
    WHEN OTHERS THEN
        ROLLBACK;
        RAISE;
END;
/

-- =====================================================
-- PROCEDURE: ADD_THESIS
-- Adds a new thesis, registers the primary supervisor,
-- and logs the audit entry.
-- =====================================================

CREATE OR REPLACE PROCEDURE ADD_THESIS(
    P_TITLE         IN VARCHAR2,
    P_ABSTRACT      IN CLOB,
    P_RESEARCHER_ID IN NUMBER,
    P_DEPT_ID       IN NUMBER,
    P_SUPERVISOR_ID IN NUMBER,
    P_ADMIN_ID      IN NUMBER
)
AS
    V_THESIS_ID NUMBER;
    V_SUPER_ID  NUMBER;
    V_LOG_ID    NUMBER;
BEGIN
    -- 1. Get next THESIS_ID
    SELECT COALESCE(MAX(THESIS_ID), 0) + 1 INTO V_THESIS_ID FROM THESES;

    -- 2. Insert thesis
    INSERT INTO THESES (THESIS_ID, RESEARCHER_ID, DEPARTMENT_ID, TITLE, ABSTRACT, SUBMISSION_DATE, VERSION_NO, STATUS)
    VALUES (V_THESIS_ID, P_RESEARCHER_ID, P_DEPT_ID, P_TITLE, P_ABSTRACT, SYSDATE, 1, 'SUBMITTED');

    -- 3. Get next SUPERVISION_ID and insert supervision record
    SELECT COALESCE(MAX(SUPERVISION_ID), 0) + 1 INTO V_SUPER_ID FROM THESIS_SUPERVISIONS;
    INSERT INTO THESIS_SUPERVISIONS (SUPERVISION_ID, THESIS_ID, SUPERVISOR_ID, SUPERVISOR_TYPE)
    VALUES (V_SUPER_ID, V_THESIS_ID, P_SUPERVISOR_ID, 'PRIMARY');

    -- 4. Log action
    SELECT COALESCE(MAX(LOG_ID), 0) + 1 INTO V_LOG_ID FROM AUDIT_LOGS;
    INSERT INTO AUDIT_LOGS (LOG_ID, USER_ID, ACTION_TYPE, TABLE_NAME, ACTION_DATE, DESCRIPTION)
    VALUES (V_LOG_ID, P_ADMIN_ID, 'INSERT', 'THESES', SYSDATE, 'Added new thesis ID ' || V_THESIS_ID || ': ' || P_TITLE);

    COMMIT;
EXCEPTION
    WHEN OTHERS THEN
        ROLLBACK;
        RAISE;
END;
/

-- =====================================================
-- PROCEDURE: DELETE_THESIS
-- Deletes a thesis and its supervisions, and logs audit.
-- =====================================================

CREATE OR REPLACE PROCEDURE DELETE_THESIS(
    P_THESIS_ID IN NUMBER,
    P_ADMIN_ID  IN NUMBER
)
AS
    V_LOG_ID NUMBER;
    V_TITLE  VARCHAR2(300);
BEGIN
    SELECT TITLE INTO V_TITLE FROM THESES WHERE THESIS_ID = P_THESIS_ID;

    -- 1. Delete associated supervisions
    DELETE FROM THESIS_SUPERVISIONS WHERE THESIS_ID = P_THESIS_ID;

    -- 2. Log action
    SELECT COALESCE(MAX(LOG_ID), 0) + 1 INTO V_LOG_ID FROM AUDIT_LOGS;
    INSERT INTO AUDIT_LOGS (LOG_ID, USER_ID, ACTION_TYPE, TABLE_NAME, ACTION_DATE, DESCRIPTION)
    VALUES (V_LOG_ID, P_ADMIN_ID, 'DELETE', 'THESES', SYSDATE, 'Deleted thesis ID ' || P_THESIS_ID || ': ' || V_TITLE);

    -- 3. Delete the thesis itself
    DELETE FROM THESES WHERE THESIS_ID = P_THESIS_ID;

    COMMIT;
EXCEPTION
    WHEN OTHERS THEN
        ROLLBACK;
        RAISE;
END;
/

-- =====================================================
-- PROCEDURE: UPDATE_THESIS
-- Updates an existing thesis and its primary supervisor
-- assignment, and logs audit.
-- =====================================================

CREATE OR REPLACE PROCEDURE UPDATE_THESIS(
    P_THESIS_ID     IN NUMBER,
    P_TITLE         IN VARCHAR2,
    P_ABSTRACT      IN CLOB,
    P_RESEARCHER_ID IN NUMBER,
    P_DEPT_ID       IN NUMBER,
    P_SUPERVISOR_ID IN NUMBER,
    P_ADMIN_ID      IN NUMBER
)
AS
    V_LOG_ID NUMBER;
BEGIN
    -- 1. Update thesis table details
    UPDATE THESES
    SET TITLE = P_TITLE,
        ABSTRACT = P_ABSTRACT,
        RESEARCHER_ID = P_RESEARCHER_ID,
        DEPARTMENT_ID = P_DEPT_ID
    WHERE THESIS_ID = P_THESIS_ID;

    -- 2. Update primary supervisor assignment
    UPDATE THESIS_SUPERVISIONS
    SET SUPERVISOR_ID = P_SUPERVISOR_ID
    WHERE THESIS_ID = P_THESIS_ID AND SUPERVISOR_TYPE = 'PRIMARY';

    -- If no primary supervisor existed, insert one
    IF SQL%ROWCOUNT = 0 THEN
        DECLARE
            V_SUPER_ID NUMBER;
        BEGIN
            SELECT COALESCE(MAX(SUPERVISION_ID), 0) + 1 INTO V_SUPER_ID FROM THESIS_SUPERVISIONS;
            INSERT INTO THESIS_SUPERVISIONS (SUPERVISION_ID, THESIS_ID, SUPERVISOR_ID, SUPERVISOR_TYPE)
            VALUES (V_SUPER_ID, P_THESIS_ID, P_SUPERVISOR_ID, 'PRIMARY');
        END;
    END IF;

    -- 3. Log action to audit logs
    SELECT COALESCE(MAX(LOG_ID), 0) + 1 INTO V_LOG_ID FROM AUDIT_LOGS;
    INSERT INTO AUDIT_LOGS (LOG_ID, USER_ID, ACTION_TYPE, TABLE_NAME, ACTION_DATE, DESCRIPTION)
    VALUES (V_LOG_ID, P_ADMIN_ID, 'UPDATE', 'THESES', SYSDATE, 'Updated thesis ID ' || P_THESIS_ID || ': ' || P_TITLE);

    COMMIT;
EXCEPTION
    WHEN OTHERS THEN
        ROLLBACK;
        RAISE;
END;
/

-- =====================================================
-- PROCEDURE: ADD_PAPER
-- Adds a new paper, logs audit, registers primary author
-- from researcher details, and parses co-authors list.
-- =====================================================

CREATE OR REPLACE PROCEDURE ADD_PAPER(
    P_TITLE            IN VARCHAR2,
    P_ABSTRACT         IN CLOB,
    P_KEYWORDS         IN VARCHAR2,
    P_PUBLICATION_YEAR IN NUMBER,
    P_RESEARCHER_ID    IN NUMBER,
    P_CO_AUTHORS       IN VARCHAR2,
    P_ADMIN_ID         IN NUMBER
)
AS
    V_PAPER_ID NUMBER;
    V_AUTHOR_ID NUMBER;
    V_LOG_ID NUMBER;
    V_R_FIRST_NAME VARCHAR2(100);
    V_R_LAST_NAME VARCHAR2(100);
    V_R_EMAIL VARCHAR2(100);
    V_R_INSTITUTION VARCHAR2(150);
BEGIN
    -- 1. Get next PAPER_ID
    SELECT COALESCE(MAX(PAPER_ID), 0) + 1 INTO V_PAPER_ID FROM PAPERS;

    -- 2. Insert paper
    INSERT INTO PAPERS (PAPER_ID, RESEARCHER_ID, TITLE, ABSTRACT, KEYWORDS, SUBMISSION_DATE, PUBLICATION_YEAR, STATUS)
    VALUES (V_PAPER_ID, P_RESEARCHER_ID, P_TITLE, P_ABSTRACT, P_KEYWORDS, SYSDATE, P_PUBLICATION_YEAR, 'SUBMITTED');

    -- 3. Get researcher information to insert as primary author
    SELECT FIRST_NAME, LAST_NAME, EMAIL, INSTITUTION
    INTO V_R_FIRST_NAME, V_R_LAST_NAME, V_R_EMAIL, V_R_INSTITUTION
    FROM USERS WHERE USER_ID = P_RESEARCHER_ID;

    SELECT COALESCE(MAX(AUTHOR_ID), 0) + 1 INTO V_AUTHOR_ID FROM PAPER_AUTHORS;
    INSERT INTO PAPER_AUTHORS (AUTHOR_ID, PAPER_ID, AUTHOR_NAME, EMAIL, INSTITUTION)
    VALUES (V_AUTHOR_ID, V_PAPER_ID, V_R_FIRST_NAME || ' ' || V_R_LAST_NAME, V_R_EMAIL, V_R_INSTITUTION);

    -- 4. Parse and insert optional co-authors if provided
    IF P_CO_AUTHORS IS NOT NULL AND TRIM(P_CO_AUTHORS) IS NOT NULL THEN
        DECLARE
            V_CO_AUTHORS VARCHAR2(4000) := P_CO_AUTHORS;
            V_NAME VARCHAR2(100);
            V_POS NUMBER;
            V_CO_EMAIL VARCHAR2(100);
            V_CO_INST VARCHAR2(150);
        BEGIN
            LOOP
                V_POS := INSTR(V_CO_AUTHORS, ',');
                IF V_POS > 0 THEN
                    V_NAME := TRIM(SUBSTR(V_CO_AUTHORS, 1, V_POS - 1));
                    V_CO_AUTHORS := SUBSTR(V_CO_AUTHORS, V_POS + 1);
                ELSE
                    V_NAME := TRIM(V_CO_AUTHORS);
                    V_CO_AUTHORS := NULL;
                END IF;

                IF V_NAME IS NOT NULL AND TRIM(V_NAME) IS NOT NULL THEN
                    BEGIN
                        SELECT EMAIL, INSTITUTION
                        INTO V_CO_EMAIL, V_CO_INST
                        FROM USERS
                        WHERE LOWER(FIRST_NAME || ' ' || LAST_NAME) = LOWER(V_NAME)
                          AND ROWNUM = 1;
                    EXCEPTION
                        WHEN NO_DATA_FOUND THEN
                            V_CO_EMAIL := NULL;
                            V_CO_INST := NULL;
                    END;

                    SELECT COALESCE(MAX(AUTHOR_ID), 0) + 1 INTO V_AUTHOR_ID FROM PAPER_AUTHORS;
                    INSERT INTO PAPER_AUTHORS (AUTHOR_ID, PAPER_ID, AUTHOR_NAME, EMAIL, INSTITUTION)
                    VALUES (V_AUTHOR_ID, V_PAPER_ID, V_NAME, V_CO_EMAIL, V_CO_INST);
                END IF;

                EXIT WHEN V_CO_AUTHORS IS NULL OR V_POS = 0;
            END LOOP;
        END;
    END IF;

    -- 5. Log action
    SELECT COALESCE(MAX(LOG_ID), 0) + 1 INTO V_LOG_ID FROM AUDIT_LOGS;
    INSERT INTO AUDIT_LOGS (LOG_ID, USER_ID, ACTION_TYPE, TABLE_NAME, ACTION_DATE, DESCRIPTION)
    VALUES (V_LOG_ID, P_ADMIN_ID, 'INSERT', 'PAPERS', SYSDATE, 'Added new paper ID ' || V_PAPER_ID || ': ' || P_TITLE);

    COMMIT;
EXCEPTION
    WHEN OTHERS THEN
        ROLLBACK;
        RAISE;
END;
/

-- =====================================================
-- PROCEDURE: EDIT_PAPER
-- Updates an existing paper, deletes and re-inserts its
-- author and co-author records, and logs the audit entry.
-- =====================================================

CREATE OR REPLACE PROCEDURE EDIT_PAPER(
    P_PAPER_ID         IN NUMBER,
    P_TITLE            IN VARCHAR2,
    P_ABSTRACT         IN CLOB,
    P_KEYWORDS         IN VARCHAR2,
    P_PUBLICATION_YEAR IN NUMBER,
    P_RESEARCHER_ID    IN NUMBER,
    P_CO_AUTHORS       IN VARCHAR2,
    P_ADMIN_ID         IN NUMBER
)
AS
    V_AUTHOR_ID NUMBER;
    V_LOG_ID NUMBER;
    V_R_FIRST_NAME VARCHAR2(100);
    V_R_LAST_NAME VARCHAR2(100);
    V_R_EMAIL VARCHAR2(100);
    V_R_INSTITUTION VARCHAR2(150);
BEGIN
    -- 1. Update paper details
    UPDATE PAPERS
    SET TITLE = P_TITLE,
        ABSTRACT = P_ABSTRACT,
        KEYWORDS = P_KEYWORDS,
        PUBLICATION_YEAR = P_PUBLICATION_YEAR,
        RESEARCHER_ID = P_RESEARCHER_ID
    WHERE PAPER_ID = P_PAPER_ID;

    -- 2. Clear existing authors for this paper
    DELETE FROM PAPER_AUTHORS WHERE PAPER_ID = P_PAPER_ID;

    -- 3. Get researcher information to insert as primary author
    SELECT FIRST_NAME, LAST_NAME, EMAIL, INSTITUTION
    INTO V_R_FIRST_NAME, V_R_LAST_NAME, V_R_EMAIL, V_R_INSTITUTION
    FROM USERS WHERE USER_ID = P_RESEARCHER_ID;

    SELECT COALESCE(MAX(AUTHOR_ID), 0) + 1 INTO V_AUTHOR_ID FROM PAPER_AUTHORS;
    INSERT INTO PAPER_AUTHORS (AUTHOR_ID, PAPER_ID, AUTHOR_NAME, EMAIL, INSTITUTION)
    VALUES (V_AUTHOR_ID, P_PAPER_ID, V_R_FIRST_NAME || ' ' || V_R_LAST_NAME, V_R_EMAIL, V_R_INSTITUTION);

    -- 4. Parse and insert optional co-authors if provided
    IF P_CO_AUTHORS IS NOT NULL AND TRIM(P_CO_AUTHORS) IS NOT NULL THEN
        DECLARE
            V_CO_AUTHORS VARCHAR2(4000) := P_CO_AUTHORS;
            V_NAME VARCHAR2(100);
            V_POS NUMBER;
            V_CO_EMAIL VARCHAR2(100);
            V_CO_INST VARCHAR2(150);
        BEGIN
            LOOP
                V_POS := INSTR(V_CO_AUTHORS, ',');
                IF V_POS > 0 THEN
                    V_NAME := TRIM(SUBSTR(V_CO_AUTHORS, 1, V_POS - 1));
                    V_CO_AUTHORS := SUBSTR(V_CO_AUTHORS, V_POS + 1);
                ELSE
                    V_NAME := TRIM(V_CO_AUTHORS);
                    V_CO_AUTHORS := NULL;
                END IF;

                IF V_NAME IS NOT NULL AND TRIM(V_NAME) IS NOT NULL THEN
                    BEGIN
                        SELECT EMAIL, INSTITUTION
                        INTO V_CO_EMAIL, V_CO_INST
                        FROM USERS
                        WHERE LOWER(FIRST_NAME || ' ' || LAST_NAME) = LOWER(V_NAME)
                          AND ROWNUM = 1;
                    EXCEPTION
                        WHEN NO_DATA_FOUND THEN
                            V_CO_EMAIL := NULL;
                            V_CO_INST := NULL;
                    END;

                    SELECT COALESCE(MAX(AUTHOR_ID), 0) + 1 INTO V_AUTHOR_ID FROM PAPER_AUTHORS;
                    INSERT INTO PAPER_AUTHORS (AUTHOR_ID, PAPER_ID, AUTHOR_NAME, EMAIL, INSTITUTION)
                    VALUES (V_AUTHOR_ID, P_PAPER_ID, V_NAME, V_CO_EMAIL, V_CO_INST);
                END IF;

                EXIT WHEN V_CO_AUTHORS IS NULL OR V_POS = 0;
            END LOOP;
        END;
    END IF;

    -- 5. Log action
    SELECT COALESCE(MAX(LOG_ID), 0) + 1 INTO V_LOG_ID FROM AUDIT_LOGS;
    INSERT INTO AUDIT_LOGS (LOG_ID, USER_ID, ACTION_TYPE, TABLE_NAME, ACTION_DATE, DESCRIPTION)
    VALUES (V_LOG_ID, P_ADMIN_ID, 'UPDATE', 'PAPERS', SYSDATE, 'Updated paper ID ' || P_PAPER_ID || ': ' || P_TITLE);

    COMMIT;
EXCEPTION
    WHEN OTHERS THEN
        ROLLBACK;
        RAISE;
END;
/

-- =====================================================
-- PROCEDURE: DELETE_PAPER
-- Deletes a paper, its authors, assignments, and reviews,
-- and logs the audit entry.
-- =====================================================

CREATE OR REPLACE PROCEDURE DELETE_PAPER(
    P_PAPER_ID IN NUMBER,
    P_ADMIN_ID IN NUMBER
)
AS
    V_LOG_ID NUMBER;
    V_TITLE  VARCHAR2(300);
BEGIN
    SELECT TITLE INTO V_TITLE FROM PAPERS WHERE PAPER_ID = P_PAPER_ID;

    -- 1. Delete associated reviews
    DELETE FROM REVIEWS
    WHERE ASSIGNMENT_ID IN (
        SELECT ASSIGNMENT_ID FROM REVIEW_ASSIGNMENTS WHERE PAPER_ID = P_PAPER_ID
    );

    -- 2. Delete review assignments
    DELETE FROM REVIEW_ASSIGNMENTS WHERE PAPER_ID = P_PAPER_ID;

    -- 3. Delete authors
    DELETE FROM PAPER_AUTHORS WHERE PAPER_ID = P_PAPER_ID;

    -- 4. Log action
    SELECT COALESCE(MAX(LOG_ID), 0) + 1 INTO V_LOG_ID FROM AUDIT_LOGS;
    INSERT INTO AUDIT_LOGS (LOG_ID, USER_ID, ACTION_TYPE, TABLE_NAME, ACTION_DATE, DESCRIPTION)
    VALUES (V_LOG_ID, P_ADMIN_ID, 'DELETE', 'PAPERS', SYSDATE, 'Deleted paper ID ' || P_PAPER_ID || ': ' || V_TITLE);

    -- 5. Delete paper itself
    DELETE FROM PAPERS WHERE PAPER_ID = P_PAPER_ID;

    COMMIT;
EXCEPTION
    WHEN OTHERS THEN
        ROLLBACK;
        RAISE;
END;
/

-- =====================================================
-- PROCEDURE: REVIEW_THESIS
-- Updates the status of a thesis (e.g. APPROVED or REJECTED)
-- and logs the review action to AUDIT_LOGS.
-- =====================================================

CREATE OR REPLACE PROCEDURE REVIEW_THESIS(
    P_THESIS_ID IN NUMBER,
    P_STATUS    IN VARCHAR2,
    P_USER_ID   IN NUMBER
)
AS
    V_LOG_ID NUMBER;
    V_TITLE  VARCHAR2(300);
BEGIN
    -- 1. Update status
    UPDATE THESES
    SET STATUS = P_STATUS
    WHERE THESIS_ID = P_THESIS_ID;

    -- 2. Log action to Audit Logs
    SELECT TITLE INTO V_TITLE FROM THESES WHERE THESIS_ID = P_THESIS_ID;
    SELECT COALESCE(MAX(LOG_ID), 0) + 1 INTO V_LOG_ID FROM AUDIT_LOGS;
    INSERT INTO AUDIT_LOGS (LOG_ID, USER_ID, ACTION_TYPE, TABLE_NAME, ACTION_DATE, DESCRIPTION)
    VALUES (V_LOG_ID, P_USER_ID, 'UPDATE', 'THESES', SYSDATE, 'Reviewed thesis ID ' || P_THESIS_ID || ' (' || V_TITLE || ') - Set status to ' || P_STATUS);

    COMMIT;
EXCEPTION
    WHEN OTHERS THEN
        ROLLBACK;
        RAISE;
END;
/
