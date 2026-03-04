--
-- PostgreSQL database dump
--

\restrict KZ2wU6jPQAtnJrspAITWiNZMrd2lWhVwKpOnDvfsMmTgO0BkjgZdG6xmHDwyeKC

-- Dumped from database version 16.11
-- Dumped by pg_dump version 16.11

-- Started on 2026-03-04 10:37:33

SET statement_timeout = 0;
SET lock_timeout = 0;
SET idle_in_transaction_session_timeout = 0;
SET client_encoding = 'UTF8';
SET standard_conforming_strings = on;
SELECT pg_catalog.set_config('search_path', '', false);
SET check_function_bodies = false;
SET xmloption = content;
SET client_min_messages = warning;
SET row_security = off;

--
-- TOC entry 2 (class 3079 OID 16621)
-- Name: pgcrypto; Type: EXTENSION; Schema: -; Owner: -
--

CREATE EXTENSION IF NOT EXISTS pgcrypto WITH SCHEMA public;


--
-- TOC entry 5057 (class 0 OID 0)
-- Dependencies: 2
-- Name: EXTENSION pgcrypto; Type: COMMENT; Schema: -; Owner: 
--

COMMENT ON EXTENSION pgcrypto IS 'cryptographic functions';


--
-- TOC entry 902 (class 1247 OID 16659)
-- Name: request_status; Type: TYPE; Schema: public; Owner: postgres
--

CREATE TYPE public.request_status AS ENUM (
    'pending',
    'accepted',
    'rejected'
);


ALTER TYPE public.request_status OWNER TO postgres;

--
-- TOC entry 288 (class 1255 OID 16809)
-- Name: accept_request(integer, integer); Type: FUNCTION; Schema: public; Owner: postgres
--

CREATE FUNCTION public.accept_request(p_request_id integer, p_admin_id integer) RETURNS void
    LANGUAGE plpgsql
    AS $$
DECLARE
  ri RECORD;
  before_s INTEGER;
  after_s INTEGER;
BEGIN
  -- Validar que quien acepta sea admin
  IF NOT EXISTS (
    SELECT 1 FROM users 
    WHERE id = p_admin_id AND role = 'admin'
  ) THEN
    RAISE EXCEPTION 'Solo un admin puede aceptar solicitudes';
  END IF;

  -- Validar que la solicitud exista y esté pendiente
  IF NOT EXISTS (
    SELECT 1 FROM requests 
    WHERE id = p_request_id AND status = 'pending'
  ) THEN
    RAISE EXCEPTION 'Solicitud no existe o no está pendiente';
  END IF;

  -- 1) VALIDAR STOCK DE INSERTOS
  FOR ri IN 
    SELECT inserto_id AS item_id, cantidad 
    FROM request_items 
    WHERE request_id = p_request_id
  LOOP
    SELECT stock INTO before_s
    FROM insertos
    WHERE id = ri.item_id FOR UPDATE;

    IF before_s < ri.cantidad THEN
      RAISE EXCEPTION 'Stock insuficiente para inserto %', ri.item_id;
    END IF;
  END LOOP;

  -- 2) VALIDAR STOCK DE HERRAMIENTAS
  FOR ri IN 
    SELECT tool_id AS item_id, cantidad 
    FROM request_tool_items 
    WHERE request_id = p_request_id
  LOOP
    SELECT stock INTO before_s
    FROM tools
    WHERE id = ri.item_id FOR UPDATE;

    IF before_s < ri.cantidad THEN
      RAISE EXCEPTION 'Stock insuficiente para herramienta %', ri.item_id;
    END IF;
  END LOOP;

  -- 3) DESCONTAR STOCK DE INSERTOS Y REGISTRAR MOVIMIENTO
  FOR ri IN 
    SELECT inserto_id AS item_id, cantidad 
    FROM request_items 
    WHERE request_id = p_request_id
  LOOP
    SELECT stock INTO before_s
    FROM insertos
    WHERE id = ri.item_id FOR UPDATE;

    after_s := before_s - ri.cantidad;

    UPDATE insertos
    SET stock = after_s
    WHERE id = ri.item_id;

    INSERT INTO stock_movements
    (inserto_id, change_amount, before_stock, after_stock, reason, performed_by)
    VALUES
    (ri.item_id, -ri.cantidad, before_s, after_s,
     'salida_por_solicitud', p_admin_id);
  END LOOP;

  -- 4) DESCONTAR STOCK DE HERRAMIENTAS
  --    (si más adelante quieres historial de herramientas,
  --     se puede crear una tabla similar a stock_movements)
  FOR ri IN 
    SELECT tool_id AS item_id, cantidad 
    FROM request_tool_items 
    WHERE request_id = p_request_id
  LOOP
    SELECT stock INTO before_s
    FROM tools
    WHERE id = ri.item_id FOR UPDATE;

    after_s := before_s - ri.cantidad;

    UPDATE tools
    SET stock = after_s
    WHERE id = ri.item_id;
  END LOOP;

  -- 5) ACTUALIZAR ESTADO DE LA SOLICITUD
  UPDATE requests
  SET status = 'accepted'
  WHERE id = p_request_id;

  -- 6) NOTIFICAR AL USUARIO
  INSERT INTO notifications (user_id, type, message)
  SELECT user_id,
         'request_accepted',
         'Su solicitud #' || id || ' fue aceptada.'
  FROM requests WHERE id = p_request_id;

END;
$$;


ALTER FUNCTION public.accept_request(p_request_id integer, p_admin_id integer) OWNER TO postgres;

--
-- TOC entry 287 (class 1255 OID 16810)
-- Name: reject_request(integer, integer, text); Type: FUNCTION; Schema: public; Owner: postgres
--

CREATE FUNCTION public.reject_request(p_request_id integer, p_admin_id integer, p_reason text) RETURNS void
    LANGUAGE plpgsql
    AS $$
BEGIN

  IF NOT EXISTS (
    SELECT 1 FROM users 
    WHERE id = p_admin_id AND role = 'admin'
  ) THEN
    RAISE EXCEPTION 'Solo un admin puede rechazar solicitudes';
  END IF;

  UPDATE requests
  SET status = 'rejected',
      admin_comment = p_reason
  WHERE id = p_request_id
  AND status = 'pending';

  INSERT INTO notifications (user_id, type, message)
  SELECT user_id,
         'request_rejected',
         'Su solicitud #' || id || ' fue rechazada: ' || COALESCE(p_reason,'')
  FROM requests WHERE id = p_request_id;

END;
$$;


ALTER FUNCTION public.reject_request(p_request_id integer, p_admin_id integer, p_reason text) OWNER TO postgres;

--
-- TOC entry 275 (class 1255 OID 16806)
-- Name: trigger_set_timestamp(); Type: FUNCTION; Schema: public; Owner: postgres
--

CREATE FUNCTION public.trigger_set_timestamp() RETURNS trigger
    LANGUAGE plpgsql
    AS $$
BEGIN
  NEW.updated_at = now();
  RETURN NEW;
END;
$$;


ALTER FUNCTION public.trigger_set_timestamp() OWNER TO postgres;

SET default_tablespace = '';

SET default_table_access_method = heap;

--
-- TOC entry 223 (class 1259 OID 16713)
-- Name: cart_items; Type: TABLE; Schema: public; Owner: postgres
--

CREATE TABLE public.cart_items (
    id integer NOT NULL,
    cart_id integer NOT NULL,
    inserto_id integer NOT NULL,
    cantidad integer NOT NULL,
    added_at timestamp without time zone DEFAULT now(),
    CONSTRAINT cart_items_cantidad_check CHECK ((cantidad > 0))
);


ALTER TABLE public.cart_items OWNER TO postgres;

--
-- TOC entry 222 (class 1259 OID 16712)
-- Name: cart_items_id_seq; Type: SEQUENCE; Schema: public; Owner: postgres
--

CREATE SEQUENCE public.cart_items_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.cart_items_id_seq OWNER TO postgres;

--
-- TOC entry 5058 (class 0 OID 0)
-- Dependencies: 222
-- Name: cart_items_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: postgres
--

ALTER SEQUENCE public.cart_items_id_seq OWNED BY public.cart_items.id;


--
-- TOC entry 235 (class 1259 OID 16896)
-- Name: cart_tool_items; Type: TABLE; Schema: public; Owner: postgres
--

CREATE TABLE public.cart_tool_items (
    id integer NOT NULL,
    cart_id integer NOT NULL,
    tool_id integer NOT NULL,
    cantidad integer NOT NULL,
    added_at timestamp without time zone DEFAULT now(),
    CONSTRAINT cart_tool_items_cantidad_check CHECK ((cantidad > 0))
);


ALTER TABLE public.cart_tool_items OWNER TO postgres;

--
-- TOC entry 234 (class 1259 OID 16895)
-- Name: cart_tool_items_id_seq; Type: SEQUENCE; Schema: public; Owner: postgres
--

CREATE SEQUENCE public.cart_tool_items_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.cart_tool_items_id_seq OWNER TO postgres;

--
-- TOC entry 5059 (class 0 OID 0)
-- Dependencies: 234
-- Name: cart_tool_items_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: postgres
--

ALTER SEQUENCE public.cart_tool_items_id_seq OWNED BY public.cart_tool_items.id;


--
-- TOC entry 221 (class 1259 OID 16698)
-- Name: carts; Type: TABLE; Schema: public; Owner: postgres
--

CREATE TABLE public.carts (
    id integer NOT NULL,
    user_id integer NOT NULL,
    created_at timestamp without time zone DEFAULT now()
);


ALTER TABLE public.carts OWNER TO postgres;

--
-- TOC entry 220 (class 1259 OID 16697)
-- Name: carts_id_seq; Type: SEQUENCE; Schema: public; Owner: postgres
--

CREATE SEQUENCE public.carts_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.carts_id_seq OWNER TO postgres;

--
-- TOC entry 5060 (class 0 OID 0)
-- Dependencies: 220
-- Name: carts_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: postgres
--

ALTER SEQUENCE public.carts_id_seq OWNED BY public.carts.id;


--
-- TOC entry 219 (class 1259 OID 16680)
-- Name: insertos; Type: TABLE; Schema: public; Owner: postgres
--

CREATE TABLE public.insertos (
    id integer NOT NULL,
    code character varying(80) NOT NULL,
    descripcion text,
    cutting_conditions text,
    cantidad_por_paquete integer DEFAULT 1 NOT NULL,
    photo_url text,
    stock integer DEFAULT 0 NOT NULL,
    created_at timestamp without time zone DEFAULT now(),
    updated_at timestamp without time zone DEFAULT now(),
    brand character varying(100),
    price numeric(12,2),
    detalle text,
    CONSTRAINT insertos_stock_check CHECK ((stock >= 0))
);


ALTER TABLE public.insertos OWNER TO postgres;

--
-- TOC entry 218 (class 1259 OID 16679)
-- Name: insertos_id_seq; Type: SEQUENCE; Schema: public; Owner: postgres
--

CREATE SEQUENCE public.insertos_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.insertos_id_seq OWNER TO postgres;

--
-- TOC entry 5061 (class 0 OID 0)
-- Dependencies: 218
-- Name: insertos_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: postgres
--

ALTER SEQUENCE public.insertos_id_seq OWNED BY public.insertos.id;


--
-- TOC entry 231 (class 1259 OID 16791)
-- Name: notifications; Type: TABLE; Schema: public; Owner: postgres
--

CREATE TABLE public.notifications (
    id integer NOT NULL,
    user_id integer NOT NULL,
    type character varying(60),
    message text,
    is_read boolean DEFAULT false,
    created_at timestamp without time zone DEFAULT now()
);


ALTER TABLE public.notifications OWNER TO postgres;

--
-- TOC entry 230 (class 1259 OID 16790)
-- Name: notifications_id_seq; Type: SEQUENCE; Schema: public; Owner: postgres
--

CREATE SEQUENCE public.notifications_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.notifications_id_seq OWNER TO postgres;

--
-- TOC entry 5062 (class 0 OID 0)
-- Dependencies: 230
-- Name: notifications_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: postgres
--

ALTER SEQUENCE public.notifications_id_seq OWNED BY public.notifications.id;


--
-- TOC entry 227 (class 1259 OID 16752)
-- Name: request_items; Type: TABLE; Schema: public; Owner: postgres
--

CREATE TABLE public.request_items (
    id integer NOT NULL,
    request_id integer NOT NULL,
    inserto_id integer NOT NULL,
    cantidad integer NOT NULL,
    returned boolean DEFAULT false,
    returned_date timestamp without time zone,
    unit_price numeric(12,2),
    CONSTRAINT request_items_cantidad_check CHECK ((cantidad > 0))
);


ALTER TABLE public.request_items OWNER TO postgres;

--
-- TOC entry 226 (class 1259 OID 16751)
-- Name: request_items_id_seq; Type: SEQUENCE; Schema: public; Owner: postgres
--

CREATE SEQUENCE public.request_items_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.request_items_id_seq OWNER TO postgres;

--
-- TOC entry 5063 (class 0 OID 0)
-- Dependencies: 226
-- Name: request_items_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: postgres
--

ALTER SEQUENCE public.request_items_id_seq OWNED BY public.request_items.id;


--
-- TOC entry 237 (class 1259 OID 16917)
-- Name: request_tool_items; Type: TABLE; Schema: public; Owner: postgres
--

CREATE TABLE public.request_tool_items (
    id integer NOT NULL,
    request_id integer NOT NULL,
    tool_id integer NOT NULL,
    cantidad integer NOT NULL,
    returned boolean DEFAULT false,
    returned_date timestamp without time zone,
    unit_price numeric(12,2),
    CONSTRAINT request_tool_items_cantidad_check CHECK ((cantidad > 0))
);


ALTER TABLE public.request_tool_items OWNER TO postgres;

--
-- TOC entry 225 (class 1259 OID 16734)
-- Name: requests; Type: TABLE; Schema: public; Owner: postgres
--

CREATE TABLE public.requests (
    id integer NOT NULL,
    user_id integer NOT NULL,
    status public.request_status DEFAULT 'pending'::public.request_status NOT NULL,
    created_at timestamp without time zone DEFAULT now(),
    updated_at timestamp without time zone DEFAULT now(),
    delivery_date date,
    approximate_return_date date,
    admin_comment text,
    ot character varying(50)
);


ALTER TABLE public.requests OWNER TO postgres;

--
-- TOC entry 233 (class 1259 OID 16878)
-- Name: tools; Type: TABLE; Schema: public; Owner: postgres
--

CREATE TABLE public.tools (
    id integer NOT NULL,
    code character varying(80) NOT NULL,
    brand character varying(100),
    price numeric(12,2) NOT NULL,
    stock integer DEFAULT 0 NOT NULL,
    created_at timestamp without time zone DEFAULT now(),
    updated_at timestamp without time zone DEFAULT now(),
    detalles text,
    photo_url text,
    CONSTRAINT tools_stock_check CHECK ((stock >= 0))
);


ALTER TABLE public.tools OWNER TO postgres;

--
-- TOC entry 217 (class 1259 OID 16666)
-- Name: users; Type: TABLE; Schema: public; Owner: postgres
--

CREATE TABLE public.users (
    id integer NOT NULL,
    username character varying(60) NOT NULL,
    password_hash text NOT NULL,
    dni character varying(32),
    area character varying(100),
    role character varying(20) NOT NULL,
    is_active boolean DEFAULT true NOT NULL,
    created_at timestamp without time zone DEFAULT now(),
    CONSTRAINT users_role_check CHECK (((role)::text = ANY ((ARRAY['admin'::character varying, 'user'::character varying])::text[])))
);


ALTER TABLE public.users OWNER TO postgres;

--
-- TOC entry 238 (class 1259 OID 16937)
-- Name: request_items_report; Type: VIEW; Schema: public; Owner: postgres
--

CREATE VIEW public.request_items_report AS
 SELECT r.id AS request_id,
    r.ot,
    r.user_id,
    u.username,
    r.created_at,
    r.delivery_date,
    r.approximate_return_date,
    'inserto'::text AS item_type,
    i.id AS item_id,
    i.code AS item_code,
    i.brand AS item_brand,
    ri.cantidad,
    COALESCE(ri.unit_price, i.price) AS unit_price,
    ((ri.cantidad)::numeric * COALESCE(ri.unit_price, i.price)) AS subtotal
   FROM (((public.requests r
     JOIN public.users u ON ((u.id = r.user_id)))
     JOIN public.request_items ri ON ((ri.request_id = r.id)))
     JOIN public.insertos i ON ((i.id = ri.inserto_id)))
UNION ALL
 SELECT r.id AS request_id,
    r.ot,
    r.user_id,
    u.username,
    r.created_at,
    r.delivery_date,
    r.approximate_return_date,
    'herramienta'::text AS item_type,
    t.id AS item_id,
    t.code AS item_code,
    t.brand AS item_brand,
    rti.cantidad,
    COALESCE(rti.unit_price, t.price) AS unit_price,
    ((rti.cantidad)::numeric * COALESCE(rti.unit_price, t.price)) AS subtotal
   FROM (((public.requests r
     JOIN public.users u ON ((u.id = r.user_id)))
     JOIN public.request_tool_items rti ON ((rti.request_id = r.id)))
     JOIN public.tools t ON ((t.id = rti.tool_id)));


ALTER VIEW public.request_items_report OWNER TO postgres;

--
-- TOC entry 236 (class 1259 OID 16916)
-- Name: request_tool_items_id_seq; Type: SEQUENCE; Schema: public; Owner: postgres
--

CREATE SEQUENCE public.request_tool_items_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.request_tool_items_id_seq OWNER TO postgres;

--
-- TOC entry 5064 (class 0 OID 0)
-- Dependencies: 236
-- Name: request_tool_items_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: postgres
--

ALTER SEQUENCE public.request_tool_items_id_seq OWNED BY public.request_tool_items.id;


--
-- TOC entry 224 (class 1259 OID 16733)
-- Name: requests_id_seq; Type: SEQUENCE; Schema: public; Owner: postgres
--

CREATE SEQUENCE public.requests_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.requests_id_seq OWNER TO postgres;

--
-- TOC entry 5065 (class 0 OID 0)
-- Dependencies: 224
-- Name: requests_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: postgres
--

ALTER SEQUENCE public.requests_id_seq OWNED BY public.requests.id;


--
-- TOC entry 229 (class 1259 OID 16772)
-- Name: stock_movements; Type: TABLE; Schema: public; Owner: postgres
--

CREATE TABLE public.stock_movements (
    id integer NOT NULL,
    inserto_id integer NOT NULL,
    change_amount integer NOT NULL,
    before_stock integer NOT NULL,
    after_stock integer NOT NULL,
    reason character varying(100),
    performed_by integer,
    created_at timestamp without time zone DEFAULT now()
);


ALTER TABLE public.stock_movements OWNER TO postgres;

--
-- TOC entry 228 (class 1259 OID 16771)
-- Name: stock_movements_id_seq; Type: SEQUENCE; Schema: public; Owner: postgres
--

CREATE SEQUENCE public.stock_movements_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.stock_movements_id_seq OWNER TO postgres;

--
-- TOC entry 5066 (class 0 OID 0)
-- Dependencies: 228
-- Name: stock_movements_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: postgres
--

ALTER SEQUENCE public.stock_movements_id_seq OWNED BY public.stock_movements.id;


--
-- TOC entry 232 (class 1259 OID 16877)
-- Name: tools_id_seq; Type: SEQUENCE; Schema: public; Owner: postgres
--

CREATE SEQUENCE public.tools_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.tools_id_seq OWNER TO postgres;

--
-- TOC entry 5067 (class 0 OID 0)
-- Dependencies: 232
-- Name: tools_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: postgres
--

ALTER SEQUENCE public.tools_id_seq OWNED BY public.tools.id;


--
-- TOC entry 216 (class 1259 OID 16665)
-- Name: users_id_seq; Type: SEQUENCE; Schema: public; Owner: postgres
--

CREATE SEQUENCE public.users_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.users_id_seq OWNER TO postgres;

--
-- TOC entry 5068 (class 0 OID 0)
-- Dependencies: 216
-- Name: users_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: postgres
--

ALTER SEQUENCE public.users_id_seq OWNED BY public.users.id;


--
-- TOC entry 4795 (class 2604 OID 16716)
-- Name: cart_items id; Type: DEFAULT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.cart_items ALTER COLUMN id SET DEFAULT nextval('public.cart_items_id_seq'::regclass);


--
-- TOC entry 4812 (class 2604 OID 16899)
-- Name: cart_tool_items id; Type: DEFAULT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.cart_tool_items ALTER COLUMN id SET DEFAULT nextval('public.cart_tool_items_id_seq'::regclass);


--
-- TOC entry 4793 (class 2604 OID 16701)
-- Name: carts id; Type: DEFAULT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.carts ALTER COLUMN id SET DEFAULT nextval('public.carts_id_seq'::regclass);


--
-- TOC entry 4788 (class 2604 OID 16683)
-- Name: insertos id; Type: DEFAULT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.insertos ALTER COLUMN id SET DEFAULT nextval('public.insertos_id_seq'::regclass);


--
-- TOC entry 4805 (class 2604 OID 16794)
-- Name: notifications id; Type: DEFAULT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.notifications ALTER COLUMN id SET DEFAULT nextval('public.notifications_id_seq'::regclass);


--
-- TOC entry 4801 (class 2604 OID 16755)
-- Name: request_items id; Type: DEFAULT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.request_items ALTER COLUMN id SET DEFAULT nextval('public.request_items_id_seq'::regclass);


--
-- TOC entry 4814 (class 2604 OID 16920)
-- Name: request_tool_items id; Type: DEFAULT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.request_tool_items ALTER COLUMN id SET DEFAULT nextval('public.request_tool_items_id_seq'::regclass);


--
-- TOC entry 4797 (class 2604 OID 16737)
-- Name: requests id; Type: DEFAULT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.requests ALTER COLUMN id SET DEFAULT nextval('public.requests_id_seq'::regclass);


--
-- TOC entry 4803 (class 2604 OID 16775)
-- Name: stock_movements id; Type: DEFAULT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.stock_movements ALTER COLUMN id SET DEFAULT nextval('public.stock_movements_id_seq'::regclass);


--
-- TOC entry 4808 (class 2604 OID 16881)
-- Name: tools id; Type: DEFAULT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.tools ALTER COLUMN id SET DEFAULT nextval('public.tools_id_seq'::regclass);


--
-- TOC entry 4785 (class 2604 OID 16669)
-- Name: users id; Type: DEFAULT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.users ALTER COLUMN id SET DEFAULT nextval('public.users_id_seq'::regclass);


--
-- TOC entry 5037 (class 0 OID 16713)
-- Dependencies: 223
-- Data for Name: cart_items; Type: TABLE DATA; Schema: public; Owner: postgres
--

COPY public.cart_items (id, cart_id, inserto_id, cantidad, added_at) FROM stdin;
\.


--
-- TOC entry 5049 (class 0 OID 16896)
-- Dependencies: 235
-- Data for Name: cart_tool_items; Type: TABLE DATA; Schema: public; Owner: postgres
--

COPY public.cart_tool_items (id, cart_id, tool_id, cantidad, added_at) FROM stdin;
\.


--
-- TOC entry 5035 (class 0 OID 16698)
-- Dependencies: 221
-- Data for Name: carts; Type: TABLE DATA; Schema: public; Owner: postgres
--

COPY public.carts (id, user_id, created_at) FROM stdin;
\.


--
-- TOC entry 5033 (class 0 OID 16680)
-- Dependencies: 219
-- Data for Name: insertos; Type: TABLE DATA; Schema: public; Owner: postgres
--

COPY public.insertos (id, code, descripcion, cutting_conditions, cantidad_por_paquete, photo_url, stock, created_at, updated_at, brand, price, detalle) FROM stdin;
\.


--
-- TOC entry 5045 (class 0 OID 16791)
-- Dependencies: 231
-- Data for Name: notifications; Type: TABLE DATA; Schema: public; Owner: postgres
--

COPY public.notifications (id, user_id, type, message, is_read, created_at) FROM stdin;
\.


--
-- TOC entry 5041 (class 0 OID 16752)
-- Dependencies: 227
-- Data for Name: request_items; Type: TABLE DATA; Schema: public; Owner: postgres
--

COPY public.request_items (id, request_id, inserto_id, cantidad, returned, returned_date, unit_price) FROM stdin;
\.


--
-- TOC entry 5051 (class 0 OID 16917)
-- Dependencies: 237
-- Data for Name: request_tool_items; Type: TABLE DATA; Schema: public; Owner: postgres
--

COPY public.request_tool_items (id, request_id, tool_id, cantidad, returned, returned_date, unit_price) FROM stdin;
\.


--
-- TOC entry 5039 (class 0 OID 16734)
-- Dependencies: 225
-- Data for Name: requests; Type: TABLE DATA; Schema: public; Owner: postgres
--

COPY public.requests (id, user_id, status, created_at, updated_at, delivery_date, approximate_return_date, admin_comment, ot) FROM stdin;
\.


--
-- TOC entry 5043 (class 0 OID 16772)
-- Dependencies: 229
-- Data for Name: stock_movements; Type: TABLE DATA; Schema: public; Owner: postgres
--

COPY public.stock_movements (id, inserto_id, change_amount, before_stock, after_stock, reason, performed_by, created_at) FROM stdin;
\.


--
-- TOC entry 5047 (class 0 OID 16878)
-- Dependencies: 233
-- Data for Name: tools; Type: TABLE DATA; Schema: public; Owner: postgres
--

COPY public.tools (id, code, brand, price, stock, created_at, updated_at, detalles, photo_url) FROM stdin;
\.


--
-- TOC entry 5031 (class 0 OID 16666)
-- Dependencies: 217
-- Data for Name: users; Type: TABLE DATA; Schema: public; Owner: postgres
--

COPY public.users (id, username, password_hash, dni, area, role, is_active, created_at) FROM stdin;
1	admin	$2a$06$UA0tEQTZBZu35wa5QQ2sOOqwveRLpKyU1.zvVV8ldug1C14xy2Zhe	00000000	almacen	admin	t	2026-02-12 09:31:42.971168
2	user	$2a$06$G35bbycl29U/n.XHr7FIJOaq/VxWAkKfFR768FIib9VWFkUc1xHhO	11111111	produccion	user	t	2026-02-12 09:31:42.971168
3	Diego Zeballos	$2y$10$zMLI0RRa5ajVt5d5vOdoHeypSYMObINh9xUk7yEdGx4htP97jub8K	72943577	ADMINISTRACIÓN	admin	t	2026-03-04 09:32:37.315609
\.


--
-- TOC entry 5069 (class 0 OID 0)
-- Dependencies: 222
-- Name: cart_items_id_seq; Type: SEQUENCE SET; Schema: public; Owner: postgres
--

SELECT pg_catalog.setval('public.cart_items_id_seq', 1, false);


--
-- TOC entry 5070 (class 0 OID 0)
-- Dependencies: 234
-- Name: cart_tool_items_id_seq; Type: SEQUENCE SET; Schema: public; Owner: postgres
--

SELECT pg_catalog.setval('public.cart_tool_items_id_seq', 1, false);


--
-- TOC entry 5071 (class 0 OID 0)
-- Dependencies: 220
-- Name: carts_id_seq; Type: SEQUENCE SET; Schema: public; Owner: postgres
--

SELECT pg_catalog.setval('public.carts_id_seq', 1, false);


--
-- TOC entry 5072 (class 0 OID 0)
-- Dependencies: 218
-- Name: insertos_id_seq; Type: SEQUENCE SET; Schema: public; Owner: postgres
--

SELECT pg_catalog.setval('public.insertos_id_seq', 1, false);


--
-- TOC entry 5073 (class 0 OID 0)
-- Dependencies: 230
-- Name: notifications_id_seq; Type: SEQUENCE SET; Schema: public; Owner: postgres
--

SELECT pg_catalog.setval('public.notifications_id_seq', 1, false);


--
-- TOC entry 5074 (class 0 OID 0)
-- Dependencies: 226
-- Name: request_items_id_seq; Type: SEQUENCE SET; Schema: public; Owner: postgres
--

SELECT pg_catalog.setval('public.request_items_id_seq', 1, false);


--
-- TOC entry 5075 (class 0 OID 0)
-- Dependencies: 236
-- Name: request_tool_items_id_seq; Type: SEQUENCE SET; Schema: public; Owner: postgres
--

SELECT pg_catalog.setval('public.request_tool_items_id_seq', 1, false);


--
-- TOC entry 5076 (class 0 OID 0)
-- Dependencies: 224
-- Name: requests_id_seq; Type: SEQUENCE SET; Schema: public; Owner: postgres
--

SELECT pg_catalog.setval('public.requests_id_seq', 1, false);


--
-- TOC entry 5077 (class 0 OID 0)
-- Dependencies: 228
-- Name: stock_movements_id_seq; Type: SEQUENCE SET; Schema: public; Owner: postgres
--

SELECT pg_catalog.setval('public.stock_movements_id_seq', 1, false);


--
-- TOC entry 5078 (class 0 OID 0)
-- Dependencies: 232
-- Name: tools_id_seq; Type: SEQUENCE SET; Schema: public; Owner: postgres
--

SELECT pg_catalog.setval('public.tools_id_seq', 1, false);


--
-- TOC entry 5079 (class 0 OID 0)
-- Dependencies: 216
-- Name: users_id_seq; Type: SEQUENCE SET; Schema: public; Owner: postgres
--

SELECT pg_catalog.setval('public.users_id_seq', 3, true);


--
-- TOC entry 4840 (class 2606 OID 16722)
-- Name: cart_items cart_items_cart_id_inserto_id_key; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.cart_items
    ADD CONSTRAINT cart_items_cart_id_inserto_id_key UNIQUE (cart_id, inserto_id);


--
-- TOC entry 4842 (class 2606 OID 16720)
-- Name: cart_items cart_items_pkey; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.cart_items
    ADD CONSTRAINT cart_items_pkey PRIMARY KEY (id);


--
-- TOC entry 4863 (class 2606 OID 16905)
-- Name: cart_tool_items cart_tool_items_cart_id_tool_id_key; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.cart_tool_items
    ADD CONSTRAINT cart_tool_items_cart_id_tool_id_key UNIQUE (cart_id, tool_id);


--
-- TOC entry 4865 (class 2606 OID 16903)
-- Name: cart_tool_items cart_tool_items_pkey; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.cart_tool_items
    ADD CONSTRAINT cart_tool_items_pkey PRIMARY KEY (id);


--
-- TOC entry 4836 (class 2606 OID 16704)
-- Name: carts carts_pkey; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.carts
    ADD CONSTRAINT carts_pkey PRIMARY KEY (id);


--
-- TOC entry 4838 (class 2606 OID 16706)
-- Name: carts carts_user_id_key; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.carts
    ADD CONSTRAINT carts_user_id_key UNIQUE (user_id);


--
-- TOC entry 4832 (class 2606 OID 16694)
-- Name: insertos insertos_code_key; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.insertos
    ADD CONSTRAINT insertos_code_key UNIQUE (code);


--
-- TOC entry 4834 (class 2606 OID 16692)
-- Name: insertos insertos_pkey; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.insertos
    ADD CONSTRAINT insertos_pkey PRIMARY KEY (id);


--
-- TOC entry 4855 (class 2606 OID 16800)
-- Name: notifications notifications_pkey; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.notifications
    ADD CONSTRAINT notifications_pkey PRIMARY KEY (id);


--
-- TOC entry 4848 (class 2606 OID 16758)
-- Name: request_items request_items_pkey; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.request_items
    ADD CONSTRAINT request_items_pkey PRIMARY KEY (id);


--
-- TOC entry 4850 (class 2606 OID 16760)
-- Name: request_items request_items_request_id_inserto_id_key; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.request_items
    ADD CONSTRAINT request_items_request_id_inserto_id_key UNIQUE (request_id, inserto_id);


--
-- TOC entry 4867 (class 2606 OID 16924)
-- Name: request_tool_items request_tool_items_pkey; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.request_tool_items
    ADD CONSTRAINT request_tool_items_pkey PRIMARY KEY (id);


--
-- TOC entry 4869 (class 2606 OID 16926)
-- Name: request_tool_items request_tool_items_request_id_tool_id_key; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.request_tool_items
    ADD CONSTRAINT request_tool_items_request_id_tool_id_key UNIQUE (request_id, tool_id);


--
-- TOC entry 4846 (class 2606 OID 16744)
-- Name: requests requests_pkey; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.requests
    ADD CONSTRAINT requests_pkey PRIMARY KEY (id);


--
-- TOC entry 4853 (class 2606 OID 16778)
-- Name: stock_movements stock_movements_pkey; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.stock_movements
    ADD CONSTRAINT stock_movements_pkey PRIMARY KEY (id);


--
-- TOC entry 4859 (class 2606 OID 16891)
-- Name: tools tools_code_key; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.tools
    ADD CONSTRAINT tools_code_key UNIQUE (code);


--
-- TOC entry 4861 (class 2606 OID 16889)
-- Name: tools tools_pkey; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.tools
    ADD CONSTRAINT tools_pkey PRIMARY KEY (id);


--
-- TOC entry 4824 (class 2606 OID 16676)
-- Name: users users_pkey; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.users
    ADD CONSTRAINT users_pkey PRIMARY KEY (id);


--
-- TOC entry 4826 (class 2606 OID 16678)
-- Name: users users_username_key; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.users
    ADD CONSTRAINT users_username_key UNIQUE (username);


--
-- TOC entry 4827 (class 1259 OID 16874)
-- Name: idx_insertos_brand; Type: INDEX; Schema: public; Owner: postgres
--

CREATE INDEX idx_insertos_brand ON public.insertos USING btree (brand);


--
-- TOC entry 4828 (class 1259 OID 16696)
-- Name: idx_insertos_code; Type: INDEX; Schema: public; Owner: postgres
--

CREATE INDEX idx_insertos_code ON public.insertos USING btree (code);


--
-- TOC entry 4829 (class 1259 OID 16875)
-- Name: idx_insertos_cutting_conditions; Type: INDEX; Schema: public; Owner: postgres
--

CREATE INDEX idx_insertos_cutting_conditions ON public.insertos USING btree (cutting_conditions);


--
-- TOC entry 4830 (class 1259 OID 16695)
-- Name: idx_insertos_stock; Type: INDEX; Schema: public; Owner: postgres
--

CREATE INDEX idx_insertos_stock ON public.insertos USING btree (stock);


--
-- TOC entry 4843 (class 1259 OID 16876)
-- Name: idx_requests_ot; Type: INDEX; Schema: public; Owner: postgres
--

CREATE INDEX idx_requests_ot ON public.requests USING btree (ot);


--
-- TOC entry 4844 (class 1259 OID 16750)
-- Name: idx_requests_user; Type: INDEX; Schema: public; Owner: postgres
--

CREATE INDEX idx_requests_user ON public.requests USING btree (user_id);


--
-- TOC entry 4851 (class 1259 OID 16789)
-- Name: idx_stock_movements_inserto; Type: INDEX; Schema: public; Owner: postgres
--

CREATE INDEX idx_stock_movements_inserto ON public.stock_movements USING btree (inserto_id);


--
-- TOC entry 4856 (class 1259 OID 16893)
-- Name: idx_tools_brand; Type: INDEX; Schema: public; Owner: postgres
--

CREATE INDEX idx_tools_brand ON public.tools USING btree (brand);


--
-- TOC entry 4857 (class 1259 OID 16892)
-- Name: idx_tools_code; Type: INDEX; Schema: public; Owner: postgres
--

CREATE INDEX idx_tools_code ON public.tools USING btree (code);


--
-- TOC entry 4883 (class 2620 OID 16807)
-- Name: insertos trig_update_insertos; Type: TRIGGER; Schema: public; Owner: postgres
--

CREATE TRIGGER trig_update_insertos BEFORE UPDATE ON public.insertos FOR EACH ROW EXECUTE FUNCTION public.trigger_set_timestamp();


--
-- TOC entry 4884 (class 2620 OID 16808)
-- Name: requests trig_update_requests; Type: TRIGGER; Schema: public; Owner: postgres
--

CREATE TRIGGER trig_update_requests BEFORE UPDATE ON public.requests FOR EACH ROW EXECUTE FUNCTION public.trigger_set_timestamp();


--
-- TOC entry 4885 (class 2620 OID 16894)
-- Name: tools trig_update_tools; Type: TRIGGER; Schema: public; Owner: postgres
--

CREATE TRIGGER trig_update_tools BEFORE UPDATE ON public.tools FOR EACH ROW EXECUTE FUNCTION public.trigger_set_timestamp();


--
-- TOC entry 4871 (class 2606 OID 16723)
-- Name: cart_items cart_items_cart_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.cart_items
    ADD CONSTRAINT cart_items_cart_id_fkey FOREIGN KEY (cart_id) REFERENCES public.carts(id) ON DELETE CASCADE;


--
-- TOC entry 4872 (class 2606 OID 16728)
-- Name: cart_items cart_items_inserto_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.cart_items
    ADD CONSTRAINT cart_items_inserto_id_fkey FOREIGN KEY (inserto_id) REFERENCES public.insertos(id) ON DELETE RESTRICT;


--
-- TOC entry 4879 (class 2606 OID 16906)
-- Name: cart_tool_items cart_tool_items_cart_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.cart_tool_items
    ADD CONSTRAINT cart_tool_items_cart_id_fkey FOREIGN KEY (cart_id) REFERENCES public.carts(id) ON DELETE CASCADE;


--
-- TOC entry 4880 (class 2606 OID 16911)
-- Name: cart_tool_items cart_tool_items_tool_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.cart_tool_items
    ADD CONSTRAINT cart_tool_items_tool_id_fkey FOREIGN KEY (tool_id) REFERENCES public.tools(id) ON DELETE RESTRICT;


--
-- TOC entry 4870 (class 2606 OID 16707)
-- Name: carts carts_user_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.carts
    ADD CONSTRAINT carts_user_id_fkey FOREIGN KEY (user_id) REFERENCES public.users(id) ON DELETE CASCADE;


--
-- TOC entry 4878 (class 2606 OID 16801)
-- Name: notifications notifications_user_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.notifications
    ADD CONSTRAINT notifications_user_id_fkey FOREIGN KEY (user_id) REFERENCES public.users(id) ON DELETE CASCADE;


--
-- TOC entry 4874 (class 2606 OID 16766)
-- Name: request_items request_items_inserto_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.request_items
    ADD CONSTRAINT request_items_inserto_id_fkey FOREIGN KEY (inserto_id) REFERENCES public.insertos(id) ON DELETE RESTRICT;


--
-- TOC entry 4875 (class 2606 OID 16761)
-- Name: request_items request_items_request_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.request_items
    ADD CONSTRAINT request_items_request_id_fkey FOREIGN KEY (request_id) REFERENCES public.requests(id) ON DELETE CASCADE;


--
-- TOC entry 4881 (class 2606 OID 16927)
-- Name: request_tool_items request_tool_items_request_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.request_tool_items
    ADD CONSTRAINT request_tool_items_request_id_fkey FOREIGN KEY (request_id) REFERENCES public.requests(id) ON DELETE CASCADE;


--
-- TOC entry 4882 (class 2606 OID 16932)
-- Name: request_tool_items request_tool_items_tool_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.request_tool_items
    ADD CONSTRAINT request_tool_items_tool_id_fkey FOREIGN KEY (tool_id) REFERENCES public.tools(id) ON DELETE RESTRICT;


--
-- TOC entry 4873 (class 2606 OID 16745)
-- Name: requests requests_user_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.requests
    ADD CONSTRAINT requests_user_id_fkey FOREIGN KEY (user_id) REFERENCES public.users(id) ON DELETE CASCADE;


--
-- TOC entry 4876 (class 2606 OID 16779)
-- Name: stock_movements stock_movements_inserto_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.stock_movements
    ADD CONSTRAINT stock_movements_inserto_id_fkey FOREIGN KEY (inserto_id) REFERENCES public.insertos(id) ON DELETE CASCADE;


--
-- TOC entry 4877 (class 2606 OID 16784)
-- Name: stock_movements stock_movements_performed_by_fkey; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.stock_movements
    ADD CONSTRAINT stock_movements_performed_by_fkey FOREIGN KEY (performed_by) REFERENCES public.users(id);


-- Completed on 2026-03-04 10:37:33

--
-- PostgreSQL database dump complete
--

\unrestrict KZ2wU6jPQAtnJrspAITWiNZMrd2lWhVwKpOnDvfsMmTgO0BkjgZdG6xmHDwyeKC

