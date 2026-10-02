-- =====================================================================
-- AGAS: Smart Water Management and Billing System (Laravel) — PostgreSQL
-- Full schema + sample data, FRESH INSTALL. Run in pgAdmin Query Tool (or
-- psql) while connected to the awas_db database.
-- WARNING: drops and recreates the AGAS tables — any data in them is lost.
-- To upgrade an EXISTING database without losing data, run
-- "php artisan migrate" instead.
-- Sample accounts: admin, meterreader, resident1, resident2 / Password123!
-- Meter Numbers are digits only (1001-1004).
-- =====================================================================

DROP TABLE IF EXISTS public.activity_logs, public.chatbot_unanswered, public.chatbot_faqs,
    public.membership_applications, public.email_verification_otps, public.password_reset_otps,
    public.notifications, public.payments, public.water_bills, public.meter_readings,
    public.billing_rates, public.consumers, public.puroks, public.system_settings,
    public.personal_access_tokens, public.migrations, public.users,
    public.password_reset_tokens, public.failed_jobs CASCADE;

--
-- PostgreSQL database dump
--

\restrict loCdrBx9EUwwsI1lDT4zUJMlo4WH2DyhXu0wCUpSnYEeiog5f4MELdSqeyc8nn4

-- Dumped from database version 18.6
-- Dumped by pg_dump version 18.6

SET statement_timeout = 0;
SET lock_timeout = 0;
SET idle_in_transaction_session_timeout = 0;
SET transaction_timeout = 0;
SET client_encoding = 'UTF8';
SET standard_conforming_strings = on;
SELECT pg_catalog.set_config('search_path', '', false);
SET check_function_bodies = false;
SET xmloption = content;
SET client_min_messages = warning;
SET row_security = off;

SET default_tablespace = '';

SET default_table_access_method = heap;

--
-- Name: activity_logs; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.activity_logs (
    log_id integer NOT NULL,
    user_id integer,
    action character varying(255) NOT NULL,
    details character varying(500),
    ip_address character varying(45),
    created_at timestamp(0) without time zone DEFAULT CURRENT_TIMESTAMP NOT NULL
);


--
-- Name: activity_logs_log_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.activity_logs_log_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: activity_logs_log_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.activity_logs_log_id_seq OWNED BY public.activity_logs.log_id;


--
-- Name: billing_rates; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.billing_rates (
    rate_id integer NOT NULL,
    rate_name character varying(100) NOT NULL,
    min_consumption numeric(10,2) DEFAULT '0'::numeric NOT NULL,
    max_consumption numeric(10,2),
    rate_per_cubic_meter numeric(10,2) NOT NULL,
    base_charge numeric(10,2) DEFAULT '0'::numeric NOT NULL,
    penalty_percentage numeric(5,2) DEFAULT '0'::numeric NOT NULL,
    is_active boolean DEFAULT true NOT NULL,
    effective_date date NOT NULL,
    created_by integer,
    created_at timestamp(0) without time zone DEFAULT CURRENT_TIMESTAMP NOT NULL,
    updated_at timestamp(0) without time zone DEFAULT CURRENT_TIMESTAMP NOT NULL
);


--
-- Name: COLUMN billing_rates.max_consumption; Type: COMMENT; Schema: public; Owner: -
--

COMMENT ON COLUMN public.billing_rates.max_consumption IS 'NULL = no upper limit';


--
-- Name: COLUMN billing_rates.base_charge; Type: COMMENT; Schema: public; Owner: -
--

COMMENT ON COLUMN public.billing_rates.base_charge IS 'flat minimum charge for the bracket';


--
-- Name: COLUMN billing_rates.penalty_percentage; Type: COMMENT; Schema: public; Owner: -
--

COMMENT ON COLUMN public.billing_rates.penalty_percentage IS 'percentage penalty applied when overdue';


--
-- Name: billing_rates_rate_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.billing_rates_rate_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: billing_rates_rate_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.billing_rates_rate_id_seq OWNED BY public.billing_rates.rate_id;


--
-- Name: chatbot_faqs; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.chatbot_faqs (
    id integer NOT NULL,
    question character varying(255) NOT NULL,
    answer text NOT NULL,
    category character varying(255) DEFAULT 'general'::character varying NOT NULL,
    keywords character varying(500),
    status character varying(255) DEFAULT 'active'::character varying NOT NULL,
    hit_count integer DEFAULT 0 NOT NULL,
    created_by integer,
    created_at timestamp(0) without time zone DEFAULT CURRENT_TIMESTAMP NOT NULL,
    updated_at timestamp(0) without time zone DEFAULT CURRENT_TIMESTAMP NOT NULL,
    CONSTRAINT chatbot_faqs_category_check CHECK (((category)::text = ANY ((ARRAY['billing'::character varying, 'meter_reading'::character varying, 'payments'::character varying, 'account'::character varying, 'system'::character varying, 'general'::character varying])::text[]))),
    CONSTRAINT chatbot_faqs_status_check CHECK (((status)::text = ANY ((ARRAY['active'::character varying, 'inactive'::character varying])::text[])))
);


--
-- Name: COLUMN chatbot_faqs.keywords; Type: COMMENT; Schema: public; Owner: -
--

COMMENT ON COLUMN public.chatbot_faqs.keywords IS 'comma-separated keywords used for FAQ matching';


--
-- Name: chatbot_faqs_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.chatbot_faqs_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: chatbot_faqs_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.chatbot_faqs_id_seq OWNED BY public.chatbot_faqs.id;


--
-- Name: chatbot_unanswered; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.chatbot_unanswered (
    id integer NOT NULL,
    question character varying(500) NOT NULL,
    asked_by integer,
    created_at timestamp(0) without time zone DEFAULT CURRENT_TIMESTAMP NOT NULL
);


--
-- Name: chatbot_unanswered_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.chatbot_unanswered_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: chatbot_unanswered_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.chatbot_unanswered_id_seq OWNED BY public.chatbot_unanswered.id;


--
-- Name: consumers; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.consumers (
    consumer_id integer NOT NULL,
    user_id integer,
    full_name character varying(150) NOT NULL,
    address character varying(255) NOT NULL,
    purok_id integer NOT NULL,
    contact_number character varying(20),
    email character varying(150),
    is_senior boolean DEFAULT false NOT NULL,
    meter_number character varying(20),
    consumer_type character varying(20) DEFAULT 'residential'::character varying NOT NULL,
    meter_status character varying(255) DEFAULT 'active'::character varying NOT NULL,
    initial_meter_reading numeric(10,2),
    household_number character varying(30),
    connection_date date,
    status character varying(255) DEFAULT 'active'::character varying NOT NULL,
    created_by integer,
    created_at timestamp(0) without time zone DEFAULT CURRENT_TIMESTAMP NOT NULL,
    updated_at timestamp(0) without time zone DEFAULT CURRENT_TIMESTAMP NOT NULL,
    CONSTRAINT consumers_meter_status_check CHECK (((meter_status)::text = ANY ((ARRAY['active'::character varying, 'inactive'::character varying, 'maintenance'::character varying])::text[]))),
    CONSTRAINT consumers_status_check CHECK (((status)::text = ANY ((ARRAY['active'::character varying, 'disconnected'::character varying, 'inactive'::character varying])::text[])))
);


--
-- Name: COLUMN consumers.user_id; Type: COMMENT; Schema: public; Owner: -
--

COMMENT ON COLUMN public.consumers.user_id IS 'linked resident login account, nullable';


--
-- Name: COLUMN consumers.is_senior; Type: COMMENT; Schema: public; Owner: -
--

COMMENT ON COLUMN public.consumers.is_senior IS '1 = senior citizen, gets the senior discount on bills';


--
-- Name: COLUMN consumers.meter_number; Type: COMMENT; Schema: public; Owner: -
--

COMMENT ON COLUMN public.consumers.meter_number IS 'digits only, e.g. 1001 — no prefix';


--
-- Name: COLUMN consumers.consumer_type; Type: COMMENT; Schema: public; Owner: -
--

COMMENT ON COLUMN public.consumers.consumer_type IS 'residential | commercial | institutional';


--
-- Name: COLUMN consumers.created_by; Type: COMMENT; Schema: public; Owner: -
--

COMMENT ON COLUMN public.consumers.created_by IS 'staff/admin who registered consumer';


--
-- Name: consumers_consumer_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.consumers_consumer_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: consumers_consumer_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.consumers_consumer_id_seq OWNED BY public.consumers.consumer_id;


--
-- Name: email_verification_otps; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.email_verification_otps (
    id integer NOT NULL,
    user_id integer NOT NULL,
    otp_hash character varying(64) NOT NULL,
    attempts smallint DEFAULT '0'::smallint NOT NULL,
    expires_at timestamp(0) without time zone NOT NULL,
    used_at timestamp(0) without time zone,
    created_at timestamp(0) without time zone DEFAULT CURRENT_TIMESTAMP NOT NULL
);


--
-- Name: COLUMN email_verification_otps.otp_hash; Type: COMMENT; Schema: public; Owner: -
--

COMMENT ON COLUMN public.email_verification_otps.otp_hash IS 'SHA-256 hash of the 6-digit code — the plaintext code is never stored';


--
-- Name: email_verification_otps_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.email_verification_otps_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: email_verification_otps_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.email_verification_otps_id_seq OWNED BY public.email_verification_otps.id;


--
-- Name: membership_applications; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.membership_applications (
    application_id integer NOT NULL,
    reference_code character varying(20) NOT NULL,
    user_id integer,
    full_name character varying(150) NOT NULL,
    birth_date date,
    sex character varying(255),
    address character varying(255) NOT NULL,
    purok_id integer NOT NULL,
    barangay character varying(100),
    municipality character varying(100),
    province character varying(100),
    contact_number character varying(20) NOT NULL,
    email character varying(150),
    notes text,
    household_number character varying(30),
    household_members smallint,
    residence_type character varying(255),
    consumer_type character varying(20) DEFAULT 'residential'::character varying NOT NULL,
    id_type character varying(60),
    id_file character varying(80),
    id_status character varying(255) DEFAULT 'not_submitted'::character varying NOT NULL,
    face_file character varying(80),
    liveness_status character varying(20) DEFAULT 'not_performed'::character varying NOT NULL,
    face_status character varying(255) DEFAULT 'not_submitted'::character varying NOT NULL,
    email_verified_at timestamp(0) without time zone,
    submitted_at timestamp(0) without time zone,
    ip_address character varying(45),
    status character varying(255) DEFAULT 'pending_verification'::character varying NOT NULL,
    review_notes character varying(500),
    reviewed_by integer,
    reviewed_at timestamp(0) without time zone,
    consumer_id integer,
    created_at timestamp(0) without time zone DEFAULT CURRENT_TIMESTAMP NOT NULL,
    CONSTRAINT membership_applications_face_status_check CHECK (((face_status)::text = ANY ((ARRAY['not_submitted'::character varying, 'submitted'::character varying, 'for_review'::character varying, 'verified'::character varying, 'failed'::character varying])::text[]))),
    CONSTRAINT membership_applications_id_status_check CHECK (((id_status)::text = ANY ((ARRAY['not_submitted'::character varying, 'submitted'::character varying, 'verified'::character varying, 'failed'::character varying])::text[]))),
    CONSTRAINT membership_applications_residence_type_check CHECK (((residence_type)::text = ANY ((ARRAY['owned'::character varying, 'rented'::character varying, 'shared'::character varying, 'other'::character varying])::text[]))),
    CONSTRAINT membership_applications_sex_check CHECK (((sex)::text = ANY ((ARRAY['male'::character varying, 'female'::character varying])::text[]))),
    CONSTRAINT membership_applications_status_check CHECK (((status)::text = ANY ((ARRAY['pending_verification'::character varying, 'pending_review'::character varying, 'approved'::character varying, 'active'::character varying, 'rejected'::character varying])::text[])))
);


--
-- Name: COLUMN membership_applications.reference_code; Type: COMMENT; Schema: public; Owner: -
--

COMMENT ON COLUMN public.membership_applications.reference_code IS 'e.g. APP-2026-0001';


--
-- Name: COLUMN membership_applications.user_id; Type: COMMENT; Schema: public; Owner: -
--

COMMENT ON COLUMN public.membership_applications.user_id IS 'applicant login account';


--
-- Name: COLUMN membership_applications.consumer_type; Type: COMMENT; Schema: public; Owner: -
--

COMMENT ON COLUMN public.membership_applications.consumer_type IS 'residential | commercial | institutional — copied to the consumer on activation';


--
-- Name: COLUMN membership_applications.id_file; Type: COMMENT; Schema: public; Owner: -
--

COMMENT ON COLUMN public.membership_applications.id_file IS 'random file name inside storage/app/applications (never web-accessible)';


--
-- Name: COLUMN membership_applications.liveness_status; Type: COMMENT; Schema: public; Owner: -
--

COMMENT ON COLUMN public.membership_applications.liveness_status IS 'passed | not_performed — blink check done in the browser';


--
-- Name: COLUMN membership_applications.face_status; Type: COMMENT; Schema: public; Owner: -
--

COMMENT ON COLUMN public.membership_applications.face_status IS 'set by an administrator after a manual look — no automated biometric matching';


--
-- Name: COLUMN membership_applications.review_notes; Type: COMMENT; Schema: public; Owner: -
--

COMMENT ON COLUMN public.membership_applications.review_notes IS 'rejection reason shown to the applicant';


--
-- Name: COLUMN membership_applications.consumer_id; Type: COMMENT; Schema: public; Owner: -
--

COMMENT ON COLUMN public.membership_applications.consumer_id IS 'set to the consumer record created on activation';


--
-- Name: membership_applications_application_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.membership_applications_application_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: membership_applications_application_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.membership_applications_application_id_seq OWNED BY public.membership_applications.application_id;


--
-- Name: meter_readings; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.meter_readings (
    reading_id integer NOT NULL,
    consumer_id integer NOT NULL,
    billing_period character varying(7) NOT NULL,
    previous_reading numeric(10,2) DEFAULT '0'::numeric NOT NULL,
    current_reading numeric(10,2) NOT NULL,
    consumption numeric(10,2) GENERATED ALWAYS AS ((current_reading - previous_reading)) STORED NOT NULL,
    reading_date date NOT NULL,
    recorded_by integer NOT NULL,
    remarks character varying(255),
    created_at timestamp(0) without time zone DEFAULT CURRENT_TIMESTAMP NOT NULL,
    updated_at timestamp(0) without time zone DEFAULT CURRENT_TIMESTAMP NOT NULL
);


--
-- Name: COLUMN meter_readings.billing_period; Type: COMMENT; Schema: public; Owner: -
--

COMMENT ON COLUMN public.meter_readings.billing_period IS 'format YYYY-MM';


--
-- Name: COLUMN meter_readings.recorded_by; Type: COMMENT; Schema: public; Owner: -
--

COMMENT ON COLUMN public.meter_readings.recorded_by IS 'staff/admin user_id';


--
-- Name: meter_readings_reading_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.meter_readings_reading_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: meter_readings_reading_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.meter_readings_reading_id_seq OWNED BY public.meter_readings.reading_id;


--
-- Name: migrations; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.migrations (
    id integer NOT NULL,
    migration character varying(255) NOT NULL,
    batch integer NOT NULL
);


--
-- Name: migrations_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.migrations_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: migrations_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.migrations_id_seq OWNED BY public.migrations.id;


--
-- Name: notifications; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.notifications (
    notification_id integer NOT NULL,
    user_id integer NOT NULL,
    bill_id integer,
    title character varying(150) NOT NULL,
    message character varying(500) NOT NULL,
    type character varying(255) DEFAULT 'general'::character varying NOT NULL,
    is_read boolean DEFAULT false NOT NULL,
    created_at timestamp(0) without time zone DEFAULT CURRENT_TIMESTAMP NOT NULL,
    CONSTRAINT notifications_type_check CHECK (((type)::text = ANY ((ARRAY['bill_due'::character varying, 'bill_overdue'::character varying, 'payment_received'::character varying, 'general'::character varying])::text[])))
);


--
-- Name: COLUMN notifications.user_id; Type: COMMENT; Schema: public; Owner: -
--

COMMENT ON COLUMN public.notifications.user_id IS 'recipient';


--
-- Name: notifications_notification_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.notifications_notification_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: notifications_notification_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.notifications_notification_id_seq OWNED BY public.notifications.notification_id;


--
-- Name: password_reset_otps; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.password_reset_otps (
    id integer NOT NULL,
    user_id integer NOT NULL,
    otp_hash character varying(64) NOT NULL,
    attempts smallint DEFAULT '0'::smallint NOT NULL,
    expires_at timestamp(0) without time zone NOT NULL,
    used_at timestamp(0) without time zone,
    created_at timestamp(0) without time zone DEFAULT CURRENT_TIMESTAMP NOT NULL
);


--
-- Name: COLUMN password_reset_otps.otp_hash; Type: COMMENT; Schema: public; Owner: -
--

COMMENT ON COLUMN public.password_reset_otps.otp_hash IS 'SHA-256 hash of the 6-digit code — the plaintext code is never stored';


--
-- Name: password_reset_otps_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.password_reset_otps_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: password_reset_otps_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.password_reset_otps_id_seq OWNED BY public.password_reset_otps.id;


--
-- Name: payments; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.payments (
    payment_id integer NOT NULL,
    payment_reference character varying(40) NOT NULL,
    bill_id integer NOT NULL,
    consumer_id integer NOT NULL,
    amount_paid numeric(10,2) NOT NULL,
    payment_method character varying(255) DEFAULT 'cash'::character varying NOT NULL,
    payment_gateway_txn_id character varying(100),
    channel character varying(10) DEFAULT 'counter'::character varying NOT NULL,
    payment_date timestamp(0) without time zone NOT NULL,
    status character varying(255) DEFAULT 'pending'::character varying NOT NULL,
    received_by integer,
    verified_at timestamp(0) without time zone,
    rejection_reason character varying(255),
    receipt_file character varying(80),
    remarks character varying(255),
    created_at timestamp(0) without time zone DEFAULT CURRENT_TIMESTAMP NOT NULL,
    updated_at timestamp(0) without time zone DEFAULT CURRENT_TIMESTAMP NOT NULL,
    CONSTRAINT payments_payment_method_check CHECK (((payment_method)::text = ANY ((ARRAY['cash'::character varying, 'online'::character varying, 'gcash'::character varying, 'bank_transfer'::character varying, 'paymongo'::character varying, 'other'::character varying])::text[]))),
    CONSTRAINT payments_status_check CHECK (((status)::text = ANY (ARRAY['pending'::text, 'verified'::text, 'failed'::text, 'refunded'::text, 'rejected'::text])))
);


--
-- Name: COLUMN payments.payment_gateway_txn_id; Type: COMMENT; Schema: public; Owner: -
--

COMMENT ON COLUMN public.payments.payment_gateway_txn_id IS 'GCash / gateway reference number only, never card/account numbers';


--
-- Name: COLUMN payments.channel; Type: COMMENT; Schema: public; Owner: -
--

COMMENT ON COLUMN public.payments.channel IS 'counter = paid at the barangay office, online = submitted by the consumer';


--
-- Name: COLUMN payments.status; Type: COMMENT; Schema: public; Owner: -
--

COMMENT ON COLUMN public.payments.status IS 'pending = Pending Verification, verified = Paid, rejected = Rejected';


--
-- Name: COLUMN payments.received_by; Type: COMMENT; Schema: public; Owner: -
--

COMMENT ON COLUMN public.payments.received_by IS 'staff/admin who recorded/verified, NULL for self-service online';


--
-- Name: COLUMN payments.receipt_file; Type: COMMENT; Schema: public; Owner: -
--

COMMENT ON COLUMN public.payments.receipt_file IS 'optional GCash receipt screenshot (storage/app/payment-receipts)';


--
-- Name: payments_payment_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.payments_payment_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: payments_payment_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.payments_payment_id_seq OWNED BY public.payments.payment_id;


--
-- Name: personal_access_tokens; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.personal_access_tokens (
    id bigint NOT NULL,
    tokenable_type character varying(255) NOT NULL,
    tokenable_id bigint NOT NULL,
    name character varying(255) NOT NULL,
    token character varying(64) NOT NULL,
    abilities text,
    last_used_at timestamp(0) without time zone,
    expires_at timestamp(0) without time zone,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


--
-- Name: personal_access_tokens_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.personal_access_tokens_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: personal_access_tokens_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.personal_access_tokens_id_seq OWNED BY public.personal_access_tokens.id;


--
-- Name: puroks; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.puroks (
    purok_id integer NOT NULL,
    purok_name character varying(100) NOT NULL,
    description character varying(255),
    created_at timestamp(0) without time zone DEFAULT CURRENT_TIMESTAMP NOT NULL
);


--
-- Name: puroks_purok_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.puroks_purok_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: puroks_purok_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.puroks_purok_id_seq OWNED BY public.puroks.purok_id;


--
-- Name: system_settings; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.system_settings (
    setting_id integer NOT NULL,
    setting_key character varying(100) NOT NULL,
    setting_value character varying(500) NOT NULL,
    description character varying(255),
    updated_at timestamp(0) without time zone DEFAULT CURRENT_TIMESTAMP NOT NULL
);


--
-- Name: system_settings_setting_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.system_settings_setting_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: system_settings_setting_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.system_settings_setting_id_seq OWNED BY public.system_settings.setting_id;


--
-- Name: users; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.users (
    user_id integer NOT NULL,
    username character varying(50) NOT NULL,
    password_hash character varying(255) NOT NULL,
    full_name character varying(150) NOT NULL,
    email character varying(150),
    contact_number character varying(20),
    role character varying(255) DEFAULT 'resident'::character varying NOT NULL,
    status character varying(255) DEFAULT 'active'::character varying NOT NULL,
    last_login timestamp(0) without time zone,
    created_at timestamp(0) without time zone DEFAULT CURRENT_TIMESTAMP NOT NULL,
    updated_at timestamp(0) without time zone DEFAULT CURRENT_TIMESTAMP NOT NULL,
    CONSTRAINT users_role_check CHECK (((role)::text = ANY ((ARRAY['admin'::character varying, 'staff'::character varying, 'resident'::character varying, 'applicant'::character varying])::text[]))),
    CONSTRAINT users_status_check CHECK (((status)::text = ANY ((ARRAY['active'::character varying, 'inactive'::character varying, 'pending'::character varying])::text[])))
);


--
-- Name: COLUMN users.status; Type: COMMENT; Schema: public; Owner: -
--

COMMENT ON COLUMN public.users.status IS 'pending = self-registered, awaiting email verification';


--
-- Name: users_user_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.users_user_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: users_user_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.users_user_id_seq OWNED BY public.users.user_id;


--
-- Name: water_bills; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.water_bills (
    bill_id integer NOT NULL,
    bill_number character varying(30) NOT NULL,
    consumer_id integer NOT NULL,
    reading_id integer NOT NULL,
    billing_period character varying(7) NOT NULL,
    consumption numeric(10,2) NOT NULL,
    rate_id integer,
    amount_due numeric(10,2) NOT NULL,
    discount_amount numeric(10,2) DEFAULT '0'::numeric NOT NULL,
    penalty_amount numeric(10,2) DEFAULT '0'::numeric NOT NULL,
    total_amount numeric(10,2) NOT NULL,
    amount_paid numeric(10,2) DEFAULT '0'::numeric NOT NULL,
    bill_date date NOT NULL,
    due_date date NOT NULL,
    disconnection_date date,
    status character varying(255) DEFAULT 'unpaid'::character varying NOT NULL,
    generated_by integer,
    created_at timestamp(0) without time zone DEFAULT CURRENT_TIMESTAMP NOT NULL,
    updated_at timestamp(0) without time zone DEFAULT CURRENT_TIMESTAMP NOT NULL,
    CONSTRAINT water_bills_status_check CHECK (((status)::text = ANY ((ARRAY['unpaid'::character varying, 'partially_paid'::character varying, 'paid'::character varying, 'overdue'::character varying])::text[])))
);


--
-- Name: COLUMN water_bills.bill_number; Type: COMMENT; Schema: public; Owner: -
--

COMMENT ON COLUMN public.water_bills.bill_number IS 'internal reference "{meter number}-{YYYY-MM}"; screens show the Meter Number';


--
-- Name: COLUMN water_bills.amount_due; Type: COMMENT; Schema: public; Owner: -
--

COMMENT ON COLUMN public.water_bills.amount_due IS 'sub-total: minimum charge + excess charge, before discount';


--
-- Name: COLUMN water_bills.discount_amount; Type: COMMENT; Schema: public; Owner: -
--

COMMENT ON COLUMN public.water_bills.discount_amount IS 'senior citizen discount';


--
-- Name: water_bills_bill_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.water_bills_bill_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: water_bills_bill_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.water_bills_bill_id_seq OWNED BY public.water_bills.bill_id;


--
-- Name: activity_logs log_id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.activity_logs ALTER COLUMN log_id SET DEFAULT nextval('public.activity_logs_log_id_seq'::regclass);


--
-- Name: billing_rates rate_id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.billing_rates ALTER COLUMN rate_id SET DEFAULT nextval('public.billing_rates_rate_id_seq'::regclass);


--
-- Name: chatbot_faqs id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.chatbot_faqs ALTER COLUMN id SET DEFAULT nextval('public.chatbot_faqs_id_seq'::regclass);


--
-- Name: chatbot_unanswered id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.chatbot_unanswered ALTER COLUMN id SET DEFAULT nextval('public.chatbot_unanswered_id_seq'::regclass);


--
-- Name: consumers consumer_id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.consumers ALTER COLUMN consumer_id SET DEFAULT nextval('public.consumers_consumer_id_seq'::regclass);


--
-- Name: email_verification_otps id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.email_verification_otps ALTER COLUMN id SET DEFAULT nextval('public.email_verification_otps_id_seq'::regclass);


--
-- Name: membership_applications application_id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.membership_applications ALTER COLUMN application_id SET DEFAULT nextval('public.membership_applications_application_id_seq'::regclass);


--
-- Name: meter_readings reading_id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.meter_readings ALTER COLUMN reading_id SET DEFAULT nextval('public.meter_readings_reading_id_seq'::regclass);


--
-- Name: migrations id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.migrations ALTER COLUMN id SET DEFAULT nextval('public.migrations_id_seq'::regclass);


--
-- Name: notifications notification_id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.notifications ALTER COLUMN notification_id SET DEFAULT nextval('public.notifications_notification_id_seq'::regclass);


--
-- Name: password_reset_otps id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.password_reset_otps ALTER COLUMN id SET DEFAULT nextval('public.password_reset_otps_id_seq'::regclass);


--
-- Name: payments payment_id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.payments ALTER COLUMN payment_id SET DEFAULT nextval('public.payments_payment_id_seq'::regclass);


--
-- Name: personal_access_tokens id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.personal_access_tokens ALTER COLUMN id SET DEFAULT nextval('public.personal_access_tokens_id_seq'::regclass);


--
-- Name: puroks purok_id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.puroks ALTER COLUMN purok_id SET DEFAULT nextval('public.puroks_purok_id_seq'::regclass);


--
-- Name: system_settings setting_id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.system_settings ALTER COLUMN setting_id SET DEFAULT nextval('public.system_settings_setting_id_seq'::regclass);


--
-- Name: users user_id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.users ALTER COLUMN user_id SET DEFAULT nextval('public.users_user_id_seq'::regclass);


--
-- Name: water_bills bill_id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.water_bills ALTER COLUMN bill_id SET DEFAULT nextval('public.water_bills_bill_id_seq'::regclass);


--
-- Data for Name: activity_logs; Type: TABLE DATA; Schema: public; Owner: -
--

COPY public.activity_logs (log_id, user_id, action, details, ip_address, created_at) FROM stdin;
\.


--
-- Data for Name: billing_rates; Type: TABLE DATA; Schema: public; Owner: -
--

COPY public.billing_rates (rate_id, rate_name, min_consumption, max_consumption, rate_per_cubic_meter, base_charge, penalty_percentage, is_active, effective_date, created_by, created_at, updated_at) FROM stdin;
1	Minimum Charge (0-10 cu.m.)	0.00	10.00	0.00	100.00	5.00	t	2026-01-01	1	2026-09-30 17:43:01	2026-09-30 17:43:01
2	Tier 2 (11-20 cu.m.)	10.01	20.00	18.00	100.00	5.00	t	2026-01-01	1	2026-09-30 17:43:01	2026-09-30 17:43:01
3	Tier 3 (21-30 cu.m.)	20.01	30.00	22.00	100.00	5.00	t	2026-01-01	1	2026-09-30 17:43:01	2026-09-30 17:43:01
4	Tier 4 (31 cu.m. and above)	30.01	\N	28.00	100.00	5.00	t	2026-01-01	1	2026-09-30 17:43:01	2026-09-30 17:43:01
\.


--
-- Data for Name: chatbot_faqs; Type: TABLE DATA; Schema: public; Owner: -
--

COPY public.chatbot_faqs (id, question, answer, category, keywords, status, hit_count, created_by, created_at, updated_at) FROM stdin;
1	What is my current water bill?	You can view your unpaid bills anytime on the "Current Bills" page in your resident dashboard. It shows your consumption, amount due, any penalties, and your remaining balance.	billing	current bill, my bill, view bill, water bill	active	0	1	2026-09-30 17:43:01	2026-09-30 17:43:01
2	How is my water bill calculated?	Your bill = Base Charge + (billable consumption x rate per cubic meter), based on the tiered rate bracket your consumption falls into. Rates are set by the barangay — see your printed bill for the exact breakdown.	billing	calculate, computation, formula, tier, rate, bracket	active	0	1	2026-09-30 17:43:01	2026-09-30 17:43:01
3	What is my water consumption?	Your consumption is the difference between your current and previous meter readings for the billing period, measured in cubic meters (m3). Check it anytime on the "My Consumption" page.	billing	consumption, usage, cubic meter, water used	active	0	1	2026-09-30 17:43:01	2026-09-30 17:43:01
4	When is my bill due?	Your exact due date is shown on your bill and on the "Current Bills" page — it is normally set a number of days after the bill date.	billing	due date, deadline, when pay	active	0	1	2026-09-30 17:43:01	2026-09-30 17:43:01
5	What happens if my bill is overdue?	If a bill is not paid within the grace period after its due date, it is marked overdue and a penalty percentage (set by the barangay) is added to your total amount due.	billing	overdue, penalty, late payment, past due	active	0	1	2026-09-30 17:43:01	2026-09-30 17:43:01
6	Where can I view my billing history?	Open "Billing History" in your resident menu to see your past, fully paid water bills. Bills you still need to pay are under "Current Bills".	billing	billing history, past bills, previous bills	active	0	1	2026-09-30 17:43:01	2026-09-30 17:43:01
7	Why is my water bill different this month?	Bill amounts change with your actual consumption each period, and can also rise if an overdue penalty was added or if billing rates were updated. Compare your readings on the "My Consumption" page.	billing	bill different, bill changed, why higher, why increased	active	0	1	2026-09-30 17:43:01	2026-09-30 17:43:01
8	What is a meter reading?	A meter reading is the number recorded from your water meter dial by barangay staff each billing period. The difference between two consecutive readings equals your consumption for that period.	meter_reading	meter reading, what is reading	active	0	1	2026-09-30 17:43:01	2026-09-30 17:43:01
9	How is my water consumption calculated from meter readings?	Consumption = Current Meter Reading - Previous Meter Reading, in cubic meters. This is recorded by our meter reader staff and used to compute your bill.	meter_reading	consumption calculation, current reading, previous reading	active	0	1	2026-09-30 17:43:01	2026-09-30 17:43:01
10	How often is the meter read?	Meter readings are taken once every billing period, typically monthly, by barangay water staff.	meter_reading	how often, reading schedule, monthly reading	active	0	1	2026-09-30 17:43:01	2026-09-30 17:43:01
11	What should I do if I think my meter reading is incorrect?	Please contact the Barangay Adlay water office with your meter number and the reading in question. Meter readings can only be corrected by authorized staff, not through this chatbot.	meter_reading	wrong reading, incorrect reading, dispute reading	active	0	1	2026-09-30 17:43:01	2026-09-30 17:43:01
12	What should I do if I suspect a water leak?	Please report it immediately to the Barangay Adlay water office so staff can inspect your meter and connection. An unusually high consumption on your "My Consumption" page can be a sign of a leak.	meter_reading	leak, water leak, high consumption, pipe leak	active	0	1	2026-09-30 17:43:01	2026-09-30 17:43:01
13	How can I pay my water bill?	Pay in person at the barangay water office (Cash or GCash QR), or online: open "Current Bills", click "Pay Bill", scan the barangay GCash QR code with your GCash app, pay the exact amount, then click "I Have Paid" and enter your GCash reference number. Your bill shows "Pending Verification" until the water office confirms the payment.	payments	how to pay, payment methods, gcash, qr, scan, cash, online payment	active	0	1	2026-09-30 17:43:01	2026-09-30 17:43:01
14	How can I check if my payment was recorded?	Open "Payment History" in your resident menu — every payment you have made is listed there along with its status: pending, verified, failed, or refunded.	payments	check payment, payment recorded, confirm payment	active	0	1	2026-09-30 17:43:01	2026-09-30 17:43:01
15	Where can I see my payment history?	Go to "Payment History" in your resident dashboard menu to see all your submitted and verified payments.	payments	payment history, past payments	active	0	1	2026-09-30 17:43:01	2026-09-30 17:43:01
16	Why is my payment still pending?	Online GCash payments stay "Pending Verification" until the barangay water office matches your GCash reference number with the money received. Once verified, your bill changes to "Paid". If it is rejected, you will see the reason and can pay again.	payments	payment pending, still pending, not verified, pending verification	active	0	1	2026-09-30 17:43:01	2026-09-30 17:43:01
17	What should I do if my payment was not reflected?	If it has been more than a few business days and your payment is still not verified or applied to your bill, please contact the Barangay Adlay water office with your payment reference number.	payments	payment not reflected, missing payment, payment not applied	active	0	1	2026-09-30 17:43:01	2026-09-30 17:43:01
18	How do I register?	Go to the "Create Account" page and enter your meter number (numbers only), full name, and purok exactly as registered with the barangay. If they match an existing consumer record without a linked login, your account will be created.	account	register, sign up, create account	active	0	1	2026-09-30 17:43:01	2026-09-30 17:43:01
19	How do I log in?	Use the username and password you created during registration on the AGAS Login page.	account	log in, login, sign in	active	0	1	2026-09-30 17:43:01	2026-09-30 17:43:01
20	How can I update my account information?	Go to "My Profile" in your resident menu to update your email and contact number, or to change your password.	account	update profile, update information, edit account	active	0	1	2026-09-30 17:43:01	2026-09-30 17:43:01
21	I forgot my password. What should I do?	For security reasons, this chatbot cannot reset passwords. Please contact the Barangay Adlay water office or system administrator, and they can issue you a new temporary password.	account	forgot password, reset password, lost password	active	0	1	2026-09-30 17:43:01	2026-09-30 17:43:01
22	How can I view my consumer information?	Your meter number, address, purok, type of consumer, and connection status are all shown on the "My Profile" page.	account	consumer information, my account, account details	active	0	1	2026-09-30 17:43:01	2026-09-30 17:43:01
23	What is AGAS?	AGAS (Smart Water Management and Billing System with Online Payment) is Barangay Adlay's digital platform for meter reading, water billing, and payment monitoring.	system	what is agas, about agas	active	0	1	2026-09-30 17:43:01	2026-09-30 17:43:01
24	What services does AGAS provide?	AGAS lets you view your water bills and consumption, review your billing and payment history, and submit online payments — all without visiting the barangay office in person.	system	services, features, what can agas do	active	0	1	2026-09-30 17:43:01	2026-09-30 17:43:01
25	How can I use the AGAS dashboard?	Your dashboard shows your latest consumption, current bill balance, due date, payment status, a consumption trend chart, and your recent payments — everything at a glance.	system	use dashboard, dashboard help	active	0	1	2026-09-30 17:43:01	2026-09-30 17:43:01
26	Where can I see my current bill?	Click "Current Bills" in your resident menu to see your unpaid bill(s) and to submit a payment.	system	current bill page, see my bill	active	0	1	2026-09-30 17:43:01	2026-09-30 17:43:01
27	Where can I see my previous bills?	Click "Billing History" in your resident menu to see all bills from previous billing periods.	system	previous bills, past bills, billing history page	active	0	1	2026-09-30 17:43:01	2026-09-30 17:43:01
\.


--
-- Data for Name: chatbot_unanswered; Type: TABLE DATA; Schema: public; Owner: -
--

COPY public.chatbot_unanswered (id, question, asked_by, created_at) FROM stdin;
\.


--
-- Data for Name: consumers; Type: TABLE DATA; Schema: public; Owner: -
--

COPY public.consumers (consumer_id, user_id, full_name, address, purok_id, contact_number, email, is_senior, meter_number, consumer_type, meter_status, initial_meter_reading, household_number, connection_date, status, created_by, created_at, updated_at) FROM stdin;
1	3	Pedro Santos	Blk 2 Lot 5, Purok 1, Adlay	1	09171000003	pedro.santos@example.com	f	1001	residential	active	\N	\N	2024-03-15	active	1	2026-09-30 17:43:01	2026-09-30 17:43:01
2	4	Maria Reyes	Purok 3, Riverside St., Adlay	3	09171000004	maria.reyes@example.com	f	1002	residential	active	\N	\N	2024-05-20	active	1	2026-09-30 17:43:01	2026-09-30 17:43:01
3	\N	Roberto Garcia	Purok 2, Adlay	2	09179998887	\N	f	1003	commercial	active	\N	\N	2023-11-10	active	1	2026-09-30 17:43:01	2026-09-30 17:43:01
4	\N	Liza Fernandez	Purok 4, Upper Adlay	4	09179998886	\N	f	1004	residential	active	\N	\N	2024-01-05	active	1	2026-09-30 17:43:01	2026-09-30 17:43:01
\.


--
-- Data for Name: email_verification_otps; Type: TABLE DATA; Schema: public; Owner: -
--

COPY public.email_verification_otps (id, user_id, otp_hash, attempts, expires_at, used_at, created_at) FROM stdin;
\.


--
-- Data for Name: membership_applications; Type: TABLE DATA; Schema: public; Owner: -
--

COPY public.membership_applications (application_id, reference_code, user_id, full_name, birth_date, sex, address, purok_id, barangay, municipality, province, contact_number, email, notes, household_number, household_members, residence_type, consumer_type, id_type, id_file, id_status, face_file, liveness_status, face_status, email_verified_at, submitted_at, ip_address, status, review_notes, reviewed_by, reviewed_at, consumer_id, created_at) FROM stdin;
\.


--
-- Data for Name: meter_readings; Type: TABLE DATA; Schema: public; Owner: -
--

COPY public.meter_readings (reading_id, consumer_id, billing_period, previous_reading, current_reading, reading_date, recorded_by, remarks, created_at, updated_at) FROM stdin;
1	1	2026-07	100.00	112.00	2026-07-28	2	Normal reading	2026-09-30 17:43:01	2026-09-30 17:43:01
2	1	2026-08	112.00	125.00	2026-08-28	2	Normal reading	2026-09-30 17:43:01	2026-09-30 17:43:01
3	2	2026-07	50.00	58.00	2026-07-28	2	Normal reading	2026-09-30 17:43:01	2026-09-30 17:43:01
4	2	2026-08	58.00	70.00	2026-08-28	2	Normal reading	2026-09-30 17:43:01	2026-09-30 17:43:01
5	3	2026-08	200.00	235.00	2026-08-28	2	High consumption - check for leaks	2026-09-30 17:43:01	2026-09-30 17:43:01
6	4	2026-08	30.00	33.00	2026-08-28	2	Normal reading	2026-09-30 17:43:01	2026-09-30 17:43:01
\.


--
-- Data for Name: migrations; Type: TABLE DATA; Schema: public; Owner: -
--

COPY public.migrations (id, migration, batch) FROM stdin;
1	2019_12_14_000001_create_personal_access_tokens_table	1
2	2026_01_01_000001_create_agas_schema	1
3	2026_01_01_000002_upgrade_legacy_agas_schema	1
4	2026_10_01_000003_meter_number_and_consumer_type	1
5	2026_10_02_000004_gcash_qr_payments	1
\.


--
-- Data for Name: notifications; Type: TABLE DATA; Schema: public; Owner: -
--

COPY public.notifications (notification_id, user_id, bill_id, title, message, type, is_read, created_at) FROM stdin;
1	3	2	New Water Bill Available	Your water bill for August 2026 (PHP 195.00) is now available. Due on 2026-09-19.	bill_due	f	2026-09-30 17:43:01
2	4	4	New Water Bill Available	Your water bill for August 2026 (PHP 180.00) is now available. Due on 2026-09-19.	bill_due	f	2026-09-30 17:43:01
3	3	1	Payment Received	We received your payment of PHP 180.00 for your July 2026 water bill. Thank you!	payment_received	t	2026-09-30 17:43:01
\.


--
-- Data for Name: password_reset_otps; Type: TABLE DATA; Schema: public; Owner: -
--

COPY public.password_reset_otps (id, user_id, otp_hash, attempts, expires_at, used_at, created_at) FROM stdin;
\.


--
-- Data for Name: payments; Type: TABLE DATA; Schema: public; Owner: -
--

COPY public.payments (payment_id, payment_reference, bill_id, consumer_id, amount_paid, payment_method, payment_gateway_txn_id, channel, payment_date, status, received_by, verified_at, rejection_reason, receipt_file, remarks, created_at, updated_at) FROM stdin;
1	PMT-2026-000001	1	1	180.00	cash	\N	counter	2026-08-01 10:15:00	verified	2	\N	\N	\N	Paid over the counter	2026-09-30 17:43:01	2026-09-30 17:43:01
2	PMT-2026-000002	3	2	150.00	gcash	GC-TXN-88213	counter	2026-08-02 14:30:00	verified	2	\N	\N	\N	Paid via GCash	2026-09-30 17:43:01	2026-09-30 17:43:01
\.


--
-- Data for Name: personal_access_tokens; Type: TABLE DATA; Schema: public; Owner: -
--

COPY public.personal_access_tokens (id, tokenable_type, tokenable_id, name, token, abilities, last_used_at, expires_at, created_at, updated_at) FROM stdin;
\.


--
-- Data for Name: puroks; Type: TABLE DATA; Schema: public; Owner: -
--

COPY public.puroks (purok_id, purok_name, description, created_at) FROM stdin;
1	Purok 1	\N	2026-09-30 17:43:01
2	Purok 2	\N	2026-09-30 17:43:01
3	Purok 3(Phase 2)	\N	2026-09-30 17:43:01
4	Purok 4(Phase 2)	\N	2026-09-30 17:43:01
5	Purok 4(Extension)	\N	2026-09-30 17:43:01
6	Purok 6	\N	2026-09-30 17:43:01
7	Purok 7	\N	2026-09-30 17:43:01
\.


--
-- Data for Name: system_settings; Type: TABLE DATA; Schema: public; Owner: -
--

COPY public.system_settings (setting_id, setting_key, setting_value, description, updated_at) FROM stdin;
1	gcash_qr_file		Barangay official GCash QR code image (uploaded in System Settings)	2026-09-30 17:43:01
2	gcash_account_name		Name shown on the barangay GCash account	2026-09-30 17:43:01
3	gcash_number		Barangay GCash mobile number	2026-09-30 17:43:01
4	barangay_name	Barangay Adlay	Name of the barangay for headers/receipts	2026-09-30 17:43:01
5	minimum_charge	150	Minimum water charge in PHP, always billed	2026-09-30 17:43:01
6	minimum_cubic_meters	10	Cubic meters covered by the minimum charge	2026-09-30 17:43:01
7	excess_rate_per_cubic_meter	15	PHP per cubic meter beyond the minimum	2026-09-30 17:43:01
8	senior_discount_percent	20	Discount percentage on the sub-total for senior citizens	2026-09-30 17:43:01
9	due_day_of_month	19	Day of the month after the billing month on which the bill is due	2026-09-30 17:43:01
10	disconnection_days	5	Days after the due date on which service is subject to disconnection	2026-09-30 17:43:01
11	overdue_grace_days	5	Days after due_date before a bill is marked overdue and penalty applies	2026-09-30 17:43:01
12	currency_symbol	PHP	Currency label used in reports	2026-09-30 17:43:01
13	contact_email	agas.adlay@example.com	Support email shown to residents	2026-09-30 17:43:01
14	contact_number	09171234567	Support contact number	2026-09-30 17:43:01
18	chatbot_ai_enabled	0	Whether the AGAS Assistant chatbot may fall back to the AI API for questions the FAQ knowledge base cannot answer (requires CHATBOT_AI_API_KEY in .env)	2026-09-30 17:43:01
\.


--
-- Data for Name: users; Type: TABLE DATA; Schema: public; Owner: -
--

COPY public.users (user_id, username, password_hash, full_name, email, contact_number, role, status, last_login, created_at, updated_at) FROM stdin;
1	admin	$2y$10$LPrbTganSZJuvOPEWbm6juYgIYUVZvHf4XfNb0ST7UjeFu4AH.aYy	Barangay AGAS Administrator	admin@agas-adlay.local	09171000001	admin	active	\N	2026-09-30 17:43:01	2026-09-30 17:43:01
2	meterreader	$2y$10$LPrbTganSZJuvOPEWbm6juYgIYUVZvHf4XfNb0ST7UjeFu4AH.aYy	Juana Dela Cruz	meterreader@agas-adlay.local	09171000002	staff	active	\N	2026-09-30 17:43:01	2026-09-30 17:43:01
3	resident1	$2y$10$LPrbTganSZJuvOPEWbm6juYgIYUVZvHf4XfNb0ST7UjeFu4AH.aYy	Pedro Santos	pedro.santos@example.com	09171000003	resident	active	\N	2026-09-30 17:43:01	2026-09-30 17:43:01
4	resident2	$2y$10$LPrbTganSZJuvOPEWbm6juYgIYUVZvHf4XfNb0ST7UjeFu4AH.aYy	Maria Reyes	maria.reyes@example.com	09171000004	resident	active	\N	2026-09-30 17:43:01	2026-09-30 17:43:01
\.


--
-- Data for Name: water_bills; Type: TABLE DATA; Schema: public; Owner: -
--

COPY public.water_bills (bill_id, bill_number, consumer_id, reading_id, billing_period, consumption, rate_id, amount_due, discount_amount, penalty_amount, total_amount, amount_paid, bill_date, due_date, disconnection_date, status, generated_by, created_at, updated_at) FROM stdin;
1	1001-2026-07	1	1	2026-07	12.00	\N	180.00	0.00	0.00	180.00	180.00	2026-07-29	2026-08-19	2026-08-24	paid	2	2026-09-30 17:43:01	2026-09-30 17:43:01
2	1001-2026-08	1	2	2026-08	13.00	\N	195.00	0.00	0.00	195.00	0.00	2026-08-29	2026-09-19	2026-09-24	unpaid	2	2026-09-30 17:43:01	2026-09-30 17:43:01
3	1002-2026-07	2	3	2026-07	8.00	\N	150.00	0.00	0.00	150.00	150.00	2026-07-29	2026-08-19	2026-08-24	paid	2	2026-09-30 17:43:01	2026-09-30 17:43:01
4	1002-2026-08	2	4	2026-08	12.00	\N	180.00	0.00	0.00	180.00	0.00	2026-08-29	2026-09-19	2026-09-24	unpaid	2	2026-09-30 17:43:01	2026-09-30 17:43:01
5	1003-2026-08	3	5	2026-08	35.00	\N	525.00	0.00	0.00	525.00	0.00	2026-08-29	2026-09-19	2026-09-24	unpaid	2	2026-09-30 17:43:01	2026-09-30 17:43:01
6	1004-2026-08	4	6	2026-08	3.00	\N	150.00	0.00	0.00	150.00	0.00	2026-08-29	2026-09-19	2026-09-24	unpaid	2	2026-09-30 17:43:01	2026-09-30 17:43:01
\.


--
-- Name: activity_logs_log_id_seq; Type: SEQUENCE SET; Schema: public; Owner: -
--

SELECT pg_catalog.setval('public.activity_logs_log_id_seq', 1, false);


--
-- Name: billing_rates_rate_id_seq; Type: SEQUENCE SET; Schema: public; Owner: -
--

SELECT pg_catalog.setval('public.billing_rates_rate_id_seq', 4, true);


--
-- Name: chatbot_faqs_id_seq; Type: SEQUENCE SET; Schema: public; Owner: -
--

SELECT pg_catalog.setval('public.chatbot_faqs_id_seq', 27, true);


--
-- Name: chatbot_unanswered_id_seq; Type: SEQUENCE SET; Schema: public; Owner: -
--

SELECT pg_catalog.setval('public.chatbot_unanswered_id_seq', 1, false);


--
-- Name: consumers_consumer_id_seq; Type: SEQUENCE SET; Schema: public; Owner: -
--

SELECT pg_catalog.setval('public.consumers_consumer_id_seq', 4, true);


--
-- Name: email_verification_otps_id_seq; Type: SEQUENCE SET; Schema: public; Owner: -
--

SELECT pg_catalog.setval('public.email_verification_otps_id_seq', 1, false);


--
-- Name: membership_applications_application_id_seq; Type: SEQUENCE SET; Schema: public; Owner: -
--

SELECT pg_catalog.setval('public.membership_applications_application_id_seq', 1, false);


--
-- Name: meter_readings_reading_id_seq; Type: SEQUENCE SET; Schema: public; Owner: -
--

SELECT pg_catalog.setval('public.meter_readings_reading_id_seq', 6, true);


--
-- Name: migrations_id_seq; Type: SEQUENCE SET; Schema: public; Owner: -
--

SELECT pg_catalog.setval('public.migrations_id_seq', 5, true);


--
-- Name: notifications_notification_id_seq; Type: SEQUENCE SET; Schema: public; Owner: -
--

SELECT pg_catalog.setval('public.notifications_notification_id_seq', 3, true);


--
-- Name: password_reset_otps_id_seq; Type: SEQUENCE SET; Schema: public; Owner: -
--

SELECT pg_catalog.setval('public.password_reset_otps_id_seq', 1, false);


--
-- Name: payments_payment_id_seq; Type: SEQUENCE SET; Schema: public; Owner: -
--

SELECT pg_catalog.setval('public.payments_payment_id_seq', 2, true);


--
-- Name: personal_access_tokens_id_seq; Type: SEQUENCE SET; Schema: public; Owner: -
--

SELECT pg_catalog.setval('public.personal_access_tokens_id_seq', 1, false);


--
-- Name: puroks_purok_id_seq; Type: SEQUENCE SET; Schema: public; Owner: -
--

SELECT pg_catalog.setval('public.puroks_purok_id_seq', 7, true);


--
-- Name: system_settings_setting_id_seq; Type: SEQUENCE SET; Schema: public; Owner: -
--

SELECT pg_catalog.setval('public.system_settings_setting_id_seq', 18, true);


--
-- Name: users_user_id_seq; Type: SEQUENCE SET; Schema: public; Owner: -
--

SELECT pg_catalog.setval('public.users_user_id_seq', 4, true);


--
-- Name: water_bills_bill_id_seq; Type: SEQUENCE SET; Schema: public; Owner: -
--

SELECT pg_catalog.setval('public.water_bills_bill_id_seq', 6, true);


--
-- Name: activity_logs activity_logs_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.activity_logs
    ADD CONSTRAINT activity_logs_pkey PRIMARY KEY (log_id);


--
-- Name: billing_rates billing_rates_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.billing_rates
    ADD CONSTRAINT billing_rates_pkey PRIMARY KEY (rate_id);


--
-- Name: chatbot_faqs chatbot_faqs_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.chatbot_faqs
    ADD CONSTRAINT chatbot_faqs_pkey PRIMARY KEY (id);


--
-- Name: chatbot_unanswered chatbot_unanswered_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.chatbot_unanswered
    ADD CONSTRAINT chatbot_unanswered_pkey PRIMARY KEY (id);


--
-- Name: consumers consumers_meter_number_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.consumers
    ADD CONSTRAINT consumers_meter_number_unique UNIQUE (meter_number);


--
-- Name: consumers consumers_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.consumers
    ADD CONSTRAINT consumers_pkey PRIMARY KEY (consumer_id);


--
-- Name: email_verification_otps email_verification_otps_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.email_verification_otps
    ADD CONSTRAINT email_verification_otps_pkey PRIMARY KEY (id);


--
-- Name: membership_applications membership_applications_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.membership_applications
    ADD CONSTRAINT membership_applications_pkey PRIMARY KEY (application_id);


--
-- Name: membership_applications membership_applications_reference_code_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.membership_applications
    ADD CONSTRAINT membership_applications_reference_code_unique UNIQUE (reference_code);


--
-- Name: meter_readings meter_readings_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.meter_readings
    ADD CONSTRAINT meter_readings_pkey PRIMARY KEY (reading_id);


--
-- Name: migrations migrations_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.migrations
    ADD CONSTRAINT migrations_pkey PRIMARY KEY (id);


--
-- Name: notifications notifications_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.notifications
    ADD CONSTRAINT notifications_pkey PRIMARY KEY (notification_id);


--
-- Name: password_reset_otps password_reset_otps_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.password_reset_otps
    ADD CONSTRAINT password_reset_otps_pkey PRIMARY KEY (id);


--
-- Name: payments payments_payment_reference_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.payments
    ADD CONSTRAINT payments_payment_reference_unique UNIQUE (payment_reference);


--
-- Name: payments payments_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.payments
    ADD CONSTRAINT payments_pkey PRIMARY KEY (payment_id);


--
-- Name: personal_access_tokens personal_access_tokens_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.personal_access_tokens
    ADD CONSTRAINT personal_access_tokens_pkey PRIMARY KEY (id);


--
-- Name: personal_access_tokens personal_access_tokens_token_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.personal_access_tokens
    ADD CONSTRAINT personal_access_tokens_token_unique UNIQUE (token);


--
-- Name: puroks puroks_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.puroks
    ADD CONSTRAINT puroks_pkey PRIMARY KEY (purok_id);


--
-- Name: puroks puroks_purok_name_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.puroks
    ADD CONSTRAINT puroks_purok_name_unique UNIQUE (purok_name);


--
-- Name: system_settings system_settings_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.system_settings
    ADD CONSTRAINT system_settings_pkey PRIMARY KEY (setting_id);


--
-- Name: system_settings system_settings_setting_key_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.system_settings
    ADD CONSTRAINT system_settings_setting_key_unique UNIQUE (setting_key);


--
-- Name: meter_readings uq_consumer_period; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.meter_readings
    ADD CONSTRAINT uq_consumer_period UNIQUE (consumer_id, billing_period);


--
-- Name: chatbot_faqs uq_faq_question; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.chatbot_faqs
    ADD CONSTRAINT uq_faq_question UNIQUE (question);


--
-- Name: users users_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.users
    ADD CONSTRAINT users_pkey PRIMARY KEY (user_id);


--
-- Name: users users_username_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.users
    ADD CONSTRAINT users_username_unique UNIQUE (username);


--
-- Name: water_bills water_bills_bill_number_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.water_bills
    ADD CONSTRAINT water_bills_bill_number_unique UNIQUE (bill_number);


--
-- Name: water_bills water_bills_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.water_bills
    ADD CONSTRAINT water_bills_pkey PRIMARY KEY (bill_id);


--
-- Name: idx_application_email; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_application_email ON public.membership_applications USING btree (email);


--
-- Name: idx_application_ip; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_application_ip ON public.membership_applications USING btree (ip_address, created_at);


--
-- Name: idx_application_purok; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_application_purok ON public.membership_applications USING btree (purok_id);


--
-- Name: idx_application_status; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_application_status ON public.membership_applications USING btree (status);


--
-- Name: idx_bill_due_date; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_bill_due_date ON public.water_bills USING btree (due_date);


--
-- Name: idx_bill_period; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_bill_period ON public.water_bills USING btree (billing_period);


--
-- Name: idx_bill_status; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_bill_status ON public.water_bills USING btree (status);


--
-- Name: idx_consumers_name; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_consumers_name ON public.consumers USING btree (full_name);


--
-- Name: idx_consumers_status; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_consumers_status ON public.consumers USING btree (status);


--
-- Name: idx_email_otp_expires; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_email_otp_expires ON public.email_verification_otps USING btree (expires_at);


--
-- Name: idx_email_otp_user; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_email_otp_user ON public.email_verification_otps USING btree (user_id);


--
-- Name: idx_faq_category; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_faq_category ON public.chatbot_faqs USING btree (category);


--
-- Name: idx_faq_status; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_faq_status ON public.chatbot_faqs USING btree (status);


--
-- Name: idx_notif_user_read; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_notif_user_read ON public.notifications USING btree (user_id, is_read);


--
-- Name: idx_otp_expires; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_otp_expires ON public.password_reset_otps USING btree (expires_at);


--
-- Name: idx_otp_user; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_otp_user ON public.password_reset_otps USING btree (user_id);


--
-- Name: idx_payment_date; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_payment_date ON public.payments USING btree (payment_date);


--
-- Name: idx_payment_status; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_payment_status ON public.payments USING btree (status);


--
-- Name: idx_rates_active; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_rates_active ON public.billing_rates USING btree (is_active);


--
-- Name: idx_reading_period; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_reading_period ON public.meter_readings USING btree (billing_period);


--
-- Name: idx_users_role; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_users_role ON public.users USING btree (role);


--
-- Name: idx_users_status; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_users_status ON public.users USING btree (status);


--
-- Name: personal_access_tokens_tokenable_type_tokenable_id_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX personal_access_tokens_tokenable_type_tokenable_id_index ON public.personal_access_tokens USING btree (tokenable_type, tokenable_id);


--
-- Name: membership_applications fk_application_consumer; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.membership_applications
    ADD CONSTRAINT fk_application_consumer FOREIGN KEY (consumer_id) REFERENCES public.consumers(consumer_id) ON UPDATE CASCADE ON DELETE SET NULL;


--
-- Name: membership_applications fk_application_purok; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.membership_applications
    ADD CONSTRAINT fk_application_purok FOREIGN KEY (purok_id) REFERENCES public.puroks(purok_id) ON UPDATE CASCADE ON DELETE RESTRICT;


--
-- Name: membership_applications fk_application_reviewer; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.membership_applications
    ADD CONSTRAINT fk_application_reviewer FOREIGN KEY (reviewed_by) REFERENCES public.users(user_id) ON UPDATE CASCADE ON DELETE SET NULL;


--
-- Name: membership_applications fk_application_user; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.membership_applications
    ADD CONSTRAINT fk_application_user FOREIGN KEY (user_id) REFERENCES public.users(user_id) ON UPDATE CASCADE ON DELETE SET NULL;


--
-- Name: water_bills fk_bill_consumer; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.water_bills
    ADD CONSTRAINT fk_bill_consumer FOREIGN KEY (consumer_id) REFERENCES public.consumers(consumer_id) ON UPDATE CASCADE ON DELETE CASCADE;


--
-- Name: water_bills fk_bill_generator; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.water_bills
    ADD CONSTRAINT fk_bill_generator FOREIGN KEY (generated_by) REFERENCES public.users(user_id) ON UPDATE CASCADE ON DELETE SET NULL;


--
-- Name: water_bills fk_bill_rate; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.water_bills
    ADD CONSTRAINT fk_bill_rate FOREIGN KEY (rate_id) REFERENCES public.billing_rates(rate_id) ON UPDATE CASCADE ON DELETE SET NULL;


--
-- Name: water_bills fk_bill_reading; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.water_bills
    ADD CONSTRAINT fk_bill_reading FOREIGN KEY (reading_id) REFERENCES public.meter_readings(reading_id) ON UPDATE CASCADE ON DELETE RESTRICT;


--
-- Name: consumers fk_consumers_creator; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.consumers
    ADD CONSTRAINT fk_consumers_creator FOREIGN KEY (created_by) REFERENCES public.users(user_id) ON UPDATE CASCADE ON DELETE SET NULL;


--
-- Name: consumers fk_consumers_purok; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.consumers
    ADD CONSTRAINT fk_consumers_purok FOREIGN KEY (purok_id) REFERENCES public.puroks(purok_id) ON UPDATE CASCADE ON DELETE RESTRICT;


--
-- Name: consumers fk_consumers_user; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.consumers
    ADD CONSTRAINT fk_consumers_user FOREIGN KEY (user_id) REFERENCES public.users(user_id) ON UPDATE CASCADE ON DELETE SET NULL;


--
-- Name: email_verification_otps fk_email_otp_user; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.email_verification_otps
    ADD CONSTRAINT fk_email_otp_user FOREIGN KEY (user_id) REFERENCES public.users(user_id) ON UPDATE CASCADE ON DELETE CASCADE;


--
-- Name: chatbot_faqs fk_faq_creator; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.chatbot_faqs
    ADD CONSTRAINT fk_faq_creator FOREIGN KEY (created_by) REFERENCES public.users(user_id) ON UPDATE CASCADE ON DELETE SET NULL;


--
-- Name: activity_logs fk_log_user; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.activity_logs
    ADD CONSTRAINT fk_log_user FOREIGN KEY (user_id) REFERENCES public.users(user_id) ON UPDATE CASCADE ON DELETE SET NULL;


--
-- Name: notifications fk_notif_bill; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.notifications
    ADD CONSTRAINT fk_notif_bill FOREIGN KEY (bill_id) REFERENCES public.water_bills(bill_id) ON UPDATE CASCADE ON DELETE CASCADE;


--
-- Name: notifications fk_notif_user; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.notifications
    ADD CONSTRAINT fk_notif_user FOREIGN KEY (user_id) REFERENCES public.users(user_id) ON UPDATE CASCADE ON DELETE CASCADE;


--
-- Name: password_reset_otps fk_otp_user; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.password_reset_otps
    ADD CONSTRAINT fk_otp_user FOREIGN KEY (user_id) REFERENCES public.users(user_id) ON UPDATE CASCADE ON DELETE CASCADE;


--
-- Name: payments fk_payment_bill; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.payments
    ADD CONSTRAINT fk_payment_bill FOREIGN KEY (bill_id) REFERENCES public.water_bills(bill_id) ON UPDATE CASCADE ON DELETE CASCADE;


--
-- Name: payments fk_payment_consumer; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.payments
    ADD CONSTRAINT fk_payment_consumer FOREIGN KEY (consumer_id) REFERENCES public.consumers(consumer_id) ON UPDATE CASCADE ON DELETE CASCADE;


--
-- Name: payments fk_payment_staff; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.payments
    ADD CONSTRAINT fk_payment_staff FOREIGN KEY (received_by) REFERENCES public.users(user_id) ON UPDATE CASCADE ON DELETE SET NULL;


--
-- Name: billing_rates fk_rates_creator; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.billing_rates
    ADD CONSTRAINT fk_rates_creator FOREIGN KEY (created_by) REFERENCES public.users(user_id) ON UPDATE CASCADE ON DELETE SET NULL;


--
-- Name: meter_readings fk_reading_consumer; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.meter_readings
    ADD CONSTRAINT fk_reading_consumer FOREIGN KEY (consumer_id) REFERENCES public.consumers(consumer_id) ON UPDATE CASCADE ON DELETE CASCADE;


--
-- Name: meter_readings fk_reading_staff; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.meter_readings
    ADD CONSTRAINT fk_reading_staff FOREIGN KEY (recorded_by) REFERENCES public.users(user_id) ON UPDATE CASCADE ON DELETE RESTRICT;


--
-- Name: chatbot_unanswered fk_unanswered_user; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.chatbot_unanswered
    ADD CONSTRAINT fk_unanswered_user FOREIGN KEY (asked_by) REFERENCES public.users(user_id) ON UPDATE CASCADE ON DELETE SET NULL;


--
-- PostgreSQL database dump complete
--

\unrestrict loCdrBx9EUwwsI1lDT4zUJMlo4WH2DyhXu0wCUpSnYEeiog5f4MELdSqeyc8nn4

