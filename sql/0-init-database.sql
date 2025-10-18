-- ##################################################################################################################
-- FOUNDATION
-- ##################################################################################################################
\ir ./core/session.sql;

-- ##################################################################################################################
-- PROJECT
-- ##################################################################################################################
-- \ir ./<MODULE>/init.sql;

-- ##################################################################################################################
-- GRANTS
-- ##################################################################################################################
DO
$do$
    DECLARE
        _sch text;
    BEGIN
        FOR _sch IN
            SELECT nspname FROM pg_namespace
            LOOP
                EXECUTE format('GRANT ALL PRIVILEGES ON SCHEMA %I TO dev', _sch);
                EXECUTE format('GRANT ALL PRIVILEGES ON ALL TABLES IN SCHEMA %I TO dev', _sch);
                EXECUTE format('GRANT ALL PRIVILEGES ON ALL SEQUENCES IN SCHEMA %I TO dev', _sch);
                EXECUTE format('GRANT ALL PRIVILEGES ON ALL FUNCTIONS IN SCHEMA %I TO dev', _sch);
            END LOOP;
    END
$do$;
