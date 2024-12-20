<?php

/*
 * This file is part of sad_spirit/pg_wrapper:
 * converter of complex PostgreSQL types and an OO wrapper for PHP's pgsql extension.
 *
 * (c) Alexey Borzov <avb@php.net>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace sad_spirit\pg_wrapper\exceptions;

/**
 * Enumeration representing PostgreSQL error codes
 *
 * The cases are copied from the list in `src/backend/utils/errcodes.txt`
 *
 * Names of the cases correspond to `spec_name` field and backing strings correspond to `sqlstate` field.
 * Unfortunately, both of these can be repeated in the list. Duplicate `sqlstate` values are covered by constants
 * serving as aliases, e.g.
 * <code>
 * case ARRAY_ELEMENT_ERROR           = '2202E';
 * public const ARRAY_SUBSCRIPT_ERROR = self::ARRAY_ELEMENT_ERROR;
 * </code>
 * while duplicate `spec_name` values are renamed, e.g.
 * <code>
 * case WARNING_STRING_DATA_RIGHT_TRUNCATION  = '01004';
 * case STRING_DATA_RIGHT_TRUNCATION          = '22001';
 * </code>
 * and a {@see canonical()} method is provided to return an un-renamed case for a renamed one. It is, however needed
 * only for renames of a few cases:
 *  - `STRING_DATA_RIGHT_TRUNCATION`
 *  - `MODIFYING_SQL_DATA_NOT_PERMITTED`
 *  - `PROHIBITED_SQL_STATEMENT_ATTEMPTED`
 *  - `READING_SQL_DATA_NOT_PERMITTED`
 *  - `NULL_VALUE_NOT_ALLOWED`
 *
 * and will return the same case for all the other ones.
 *
 * @since 3.0.0
 */
enum SqlState: string
{
    /* Class 00 - Successful Completion */
    case SUCCESSFUL_COMPLETION = '00000';

    /* Class 01 - Warning */
    case WARNING                               = '01000';
    case DYNAMIC_RESULT_SETS_RETURNED          = '0100C';
    case IMPLICIT_ZERO_BIT_PADDING             = '01008';
    case NULL_VALUE_ELIMINATED_IN_SET_FUNCTION = '01003';
    case PRIVILEGE_NOT_GRANTED                 = '01007';
    case PRIVILEGE_NOT_REVOKED                 = '01006';
    case WARNING_STRING_DATA_RIGHT_TRUNCATION  = '01004'; // name changed, code 22001 had the same one
    case DEPRECATED_FEATURE                    = '01P01';

    /* Class 02 - No Data (this is also a warning class per the SQL standard) */
    case NO_DATA                                    = '02000';
    case NO_ADDITIONAL_DYNAMIC_RESULT_SETS_RETURNED = '02001';

    /* Class 03 - SQL Statement Not Yet Complete */
    case SQL_STATEMENT_NOT_YET_COMPLETE = '03000';

    /* Class 08 - Connection Exception */
    case CONNECTION_EXCEPTION                              = '08000';
    case CONNECTION_DOES_NOT_EXIST                         = '08003';
    case CONNECTION_FAILURE                                = '08006';
    case SQLCLIENT_UNABLE_TO_ESTABLISH_SQLCONNECTION       = '08001';
    case SQLSERVER_REJECTED_ESTABLISHMENT_OF_SQLCONNECTION = '08004';
    case TRANSACTION_RESOLUTION_UNKNOWN                    = '08007';
    case PROTOCOL_VIOLATION                                = '08P01';

    /* Class 09 - Triggered Action Exception */
    case TRIGGERED_ACTION_EXCEPTION = '09000';

    /* Class 0A - Feature Not Supported */
    case FEATURE_NOT_SUPPORTED = '0A000';

    /* Class 0B - Invalid Transaction Initiation */
    case INVALID_TRANSACTION_INITIATION = '0B000';

    /* Class 0F - Locator Exception */
    case LOCATOR_EXCEPTION             = '0F000';
    case INVALID_LOCATOR_SPECIFICATION = '0F001';

    /* Class 0L - Invalid Grantor */
    case INVALID_GRANTOR         = '0L000';
    case INVALID_GRANT_OPERATION = '0LP01';

    /* Class 0P - Invalid Role Specification */
    case INVALID_ROLE_SPECIFICATION = '0P000';

    /* Class 0Z - Diagnostics Exception */
    case DIAGNOSTICS_EXCEPTION                               = '0Z000';
    case STACKED_DIAGNOSTICS_ACCESSED_WITHOUT_ACTIVE_HANDLER = '0Z002';

    /* Class 20 - Case Not Found */
    case CASE_NOT_FOUND = '20000';

    /* Class 21 - Cardinality Violation */
    case CARDINALITY_VIOLATION = '21000';

    /* Class 22 - Data Exception */
    case DATA_EXCEPTION                                  = '22000';
    case ARRAY_ELEMENT_ERROR                             = '2202E';
    public const ARRAY_SUBSCRIPT_ERROR                   = self::ARRAY_ELEMENT_ERROR;
    case CHARACTER_NOT_IN_REPERTOIRE                     = '22021';
    case DATETIME_FIELD_OVERFLOW                         = '22008';
    public const DATETIME_VALUE_OUT_OF_RANGE             = self::DATETIME_FIELD_OVERFLOW;
    case DIVISION_BY_ZERO                                = '22012';
    case ERROR_IN_ASSIGNMENT                             = '22005';
    case ESCAPE_CHARACTER_CONFLICT                       = '2200B';
    case INDICATOR_OVERFLOW                              = '22022';
    case INTERVAL_FIELD_OVERFLOW                         = '22015';
    case INVALID_ARGUMENT_FOR_LOGARITHM                  = '2201E';
    case INVALID_ARGUMENT_FOR_NTILE_FUNCTION             = '22014';
    case INVALID_ARGUMENT_FOR_NTH_VALUE_FUNCTION         = '22016';
    case INVALID_ARGUMENT_FOR_POWER_FUNCTION             = '2201F';
    case INVALID_ARGUMENT_FOR_WIDTH_BUCKET_FUNCTION      = '2201G';
    case INVALID_CHARACTER_VALUE_FOR_CAST                = '22018';
    case INVALID_DATETIME_FORMAT                         = '22007';
    case INVALID_ESCAPE_CHARACTER                        = '22019';
    case INVALID_ESCAPE_OCTET                            = '2200D';
    case INVALID_ESCAPE_SEQUENCE                         = '22025';
    case NONSTANDARD_USE_OF_ESCAPE_CHARACTER             = '22P06';
    case INVALID_INDICATOR_PARAMETER_VALUE               = '22010';
    case INVALID_PARAMETER_VALUE                         = '22023';
    case INVALID_PRECEDING_OR_FOLLOWING_SIZE             = '22013';
    case INVALID_REGULAR_EXPRESSION                      = '2201B';
    case INVALID_ROW_COUNT_IN_LIMIT_CLAUSE               = '2201W';
    case INVALID_ROW_COUNT_IN_RESULT_OFFSET_CLAUSE       = '2201X';
    case INVALID_TABLESAMPLE_ARGUMENT                    = '2202H';
    case INVALID_TABLESAMPLE_REPEAT                      = '2202G';
    case INVALID_TIME_ZONE_DISPLACEMENT_VALUE            = '22009';
    case INVALID_USE_OF_ESCAPE_CHARACTER                 = '2200C';
    case MOST_SPECIFIC_TYPE_MISMATCH                     = '2200G';
    case NULL_VALUE_NOT_ALLOWED                          = '22004';
    case NULL_VALUE_NO_INDICATOR_PARAMETER               = '22002';
    case NUMERIC_VALUE_OUT_OF_RANGE                      = '22003';
    case SEQUENCE_GENERATOR_LIMIT_EXCEEDED               = '2200H';
    case STRING_DATA_LENGTH_MISMATCH                     = '22026';
    case STRING_DATA_RIGHT_TRUNCATION                    = '22001';
    case SUBSTRING_ERROR                                 = '22011';
    case TRIM_ERROR                                      = '22027';
    case UNTERMINATED_C_STRING                           = '22024';
    case ZERO_LENGTH_CHARACTER_STRING                    = '2200F';
    case FLOATING_POINT_EXCEPTION                        = '22P01';
    case INVALID_TEXT_REPRESENTATION                     = '22P02';
    case INVALID_BINARY_REPRESENTATION                   = '22P03';
    case BAD_COPY_FILE_FORMAT                            = '22P04';
    case UNTRANSLATABLE_CHARACTER                        = '22P05';
    case NOT_AN_XML_DOCUMENT                             = '2200L';
    case INVALID_XML_DOCUMENT                            = '2200M';
    case INVALID_XML_CONTENT                             = '2200N';
    case INVALID_XML_COMMENT                             = '2200S';
    case INVALID_XML_PROCESSING_INSTRUCTION              = '2200T';
    case DUPLICATE_JSON_OBJECT_KEY_VALUE                 = '22030';
    case INVALID_ARGUMENT_FOR_SQL_JSON_DATETIME_FUNCTION = '22031';
    case INVALID_JSON_TEXT                               = '22032';
    case INVALID_SQL_JSON_SUBSCRIPT                      = '22033';
    case MORE_THAN_ONE_SQL_JSON_ITEM                     = '22034';
    case NO_SQL_JSON_ITEM                                = '22035';
    case NON_NUMERIC_SQL_JSON_ITEM                       = '22036';
    case NON_UNIQUE_KEYS_IN_A_JSON_OBJECT                = '22037';
    case SINGLETON_SQL_JSON_ITEM_REQUIRED                = '22038';
    case SQL_JSON_ARRAY_NOT_FOUND                        = '22039';
    case SQL_JSON_MEMBER_NOT_FOUND                       = '2203A';
    case SQL_JSON_NUMBER_NOT_FOUND                       = '2203B';
    case SQL_JSON_OBJECT_NOT_FOUND                       = '2203C';
    case TOO_MANY_JSON_ARRAY_ELEMENTS                    = '2203D';
    case TOO_MANY_JSON_OBJECT_MEMBERS                    = '2203E';
    case SQL_JSON_SCALAR_REQUIRED                        = '2203F';
    case SQL_JSON_ITEM_CANNOT_BE_CAST_TO_TARGET_TYPE     = '2203G';

    /* Class 23 - Integrity Constraint Violation */
    case INTEGRITY_CONSTRAINT_VIOLATION = '23000';
    case RESTRICT_VIOLATION             = '23001';
    case NOT_NULL_VIOLATION             = '23502';
    case FOREIGN_KEY_VIOLATION          = '23503';
    case UNIQUE_VIOLATION               = '23505';
    case CHECK_VIOLATION                = '23514';
    case EXCLUSION_VIOLATION            = '23P01';

    /* Class 24 - Invalid Cursor State */
    case INVALID_CURSOR_STATE = '24000';

    /* Class 25 - Invalid Transaction State */
    case INVALID_TRANSACTION_STATE                            = '25000';
    case ACTIVE_SQL_TRANSACTION                               = '25001';
    case BRANCH_TRANSACTION_ALREADY_ACTIVE                    = '25002';
    case HELD_CURSOR_REQUIRES_SAME_ISOLATION_LEVEL            = '25008';
    case INAPPROPRIATE_ACCESS_MODE_FOR_BRANCH_TRANSACTION     = '25003';
    case INAPPROPRIATE_ISOLATION_LEVEL_FOR_BRANCH_TRANSACTION = '25004';
    case NO_ACTIVE_SQL_TRANSACTION_FOR_BRANCH_TRANSACTION     = '25005';
    case READ_ONLY_SQL_TRANSACTION                            = '25006';
    case SCHEMA_AND_DATA_STATEMENT_MIXING_NOT_SUPPORTED       = '25007';
    case NO_ACTIVE_SQL_TRANSACTION                            = '25P01';
    case IN_FAILED_SQL_TRANSACTION                            = '25P02';
    case IDLE_IN_TRANSACTION_SESSION_TIMEOUT                  = '25P03';
    case ERRCODE_TRANSACTION_TIMEOUT                          = '25P04';

    /* Class 26 - Invalid SQL Statement Name */
    case INVALID_SQL_STATEMENT_NAME = '26000';

    /* Class 27 - Triggered Data Change Violation */
    case TRIGGERED_DATA_CHANGE_VIOLATION = '27000';

    /* Class 28 - Invalid Authorization Specification */
    case INVALID_AUTHORIZATION_SPECIFICATION = '28000';
    case INVALID_PASSWORD                    = '28P01';

    /* Class 2B - Dependent Privilege Descriptors Still Exist */
    case DEPENDENT_PRIVILEGE_DESCRIPTORS_STILL_EXIST = '2B000';
    case DEPENDENT_OBJECTS_STILL_EXIST               = '2BP01';

    /* Class 2D - Invalid Transaction Termination */
    case INVALID_TRANSACTION_TERMINATION = '2D000';

    /* Class 2F - SQL Routine Exception */
    case SQL_ROUTINE_EXCEPTION                 = '2F000';
    case FUNCTION_EXECUTED_NO_RETURN_STATEMENT = '2F005';
    case MODIFYING_SQL_DATA_NOT_PERMITTED      = '2F002';
    case PROHIBITED_SQL_STATEMENT_ATTEMPTED    = '2F003';
    case READING_SQL_DATA_NOT_PERMITTED        = '2F004';

    /* Class 34 - Invalid Cursor Name */
    case INVALID_CURSOR_NAME = '34000';

    /* Class 38 - External Routine Exception */
    case EXTERNAL_ROUTINE_EXCEPTION                  = '38000';
    case CONTAINING_SQL_NOT_PERMITTED                = '38001';
    case EXTERNAL_MODIFYING_SQL_DATA_NOT_PERMITTED   = '38002'; // name changed, code 2F002 had the same one
    case EXTERNAL_PROHIBITED_SQL_STATEMENT_ATTEMPTED = '38003'; // name changed, code 2F003 had the same one
    case EXTERNAL_READING_SQL_DATA_NOT_PERMITTED     = '38004'; // name changed, code 2F004 had the same one

    /* Class 39 - External Routine Invocation Exception */
    case EXTERNAL_ROUTINE_INVOCATION_EXCEPTION = '39000';
    case INVALID_SQLSTATE_RETURNED             = '39001';
    case EXTERNAL_NULL_VALUE_NOT_ALLOWED       = '39004'; // name changed, code 22004 had the same one
    case TRIGGER_PROTOCOL_VIOLATED             = '39P01';
    case SRF_PROTOCOL_VIOLATED                 = '39P02';
    case EVENT_TRIGGER_PROTOCOL_VIOLATED       = '39P03';

    /* Class 3B - Savepoint Exception */
    case SAVEPOINT_EXCEPTION             = '3B000';
    case INVALID_SAVEPOINT_SPECIFICATION = '3B001';

    /* Class 3D - Invalid Catalog Name */
    case INVALID_CATALOG_NAME = '3D000';

    /* Class 3F - Invalid Schema Name */
    case INVALID_SCHEMA_NAME = '3F000';

    /* Class 40 - Transaction Rollback */
    case TRANSACTION_ROLLBACK                       = '40000';
    case TRANSACTION_INTEGRITY_CONSTRAINT_VIOLATION = '40002';
    case SERIALIZATION_FAILURE                      = '40001';
    case STATEMENT_COMPLETION_UNKNOWN               = '40003';
    case DEADLOCK_DETECTED                          = '40P01';

    /* Class 42 - Syntax Error or Access Rule Violation */
    case SYNTAX_ERROR_OR_ACCESS_RULE_VIOLATION = '42000';
    case SYNTAX_ERROR                          = '42601';
    case INSUFFICIENT_PRIVILEGE                = '42501';
    case CANNOT_COERCE                         = '42846';
    case GROUPING_ERROR                        = '42803';
    case WINDOWING_ERROR                       = '42P20';
    case INVALID_RECURSION                     = '42P19';
    case INVALID_FOREIGN_KEY                   = '42830';
    case INVALID_NAME                          = '42602';
    case NAME_TOO_LONG                         = '42622';
    case RESERVED_NAME                         = '42939';
    case DATATYPE_MISMATCH                     = '42804';
    case INDETERMINATE_DATATYPE                = '42P18';
    case COLLATION_MISMATCH                    = '42P21';
    case INDETERMINATE_COLLATION               = '42P22';
    case WRONG_OBJECT_TYPE                     = '42809';
    case GENERATED_ALWAYS                      = '428C9';
    case UNDEFINED_COLUMN                      = '42703';
    public const UNDEFINED_CURSOR              = self::INVALID_CURSOR_NAME;
    public const UNDEFINED_DATABASE            = self::INVALID_CATALOG_NAME;
    case UNDEFINED_FUNCTION                    = '42883';
    public const UNDEFINED_PSTATEMENT          = self::INVALID_SQL_STATEMENT_NAME;
    public const UNDEFINED_SCHEMA              = self::INVALID_SCHEMA_NAME;
    case UNDEFINED_TABLE                       = '42P01';
    case UNDEFINED_PARAMETER                   = '42P02';
    case UNDEFINED_OBJECT                      = '42704';
    case DUPLICATE_COLUMN                      = '42701';
    case DUPLICATE_CURSOR                      = '42P03';
    case DUPLICATE_DATABASE                    = '42P04';
    case DUPLICATE_FUNCTION                    = '42723';
    case DUPLICATE_PREPARED_STATEMENT          = '42P05';
    case DUPLICATE_SCHEMA                      = '42P06';
    case DUPLICATE_TABLE                       = '42P07';
    case DUPLICATE_ALIAS                       = '42712';
    case DUPLICATE_OBJECT                      = '42710';
    case AMBIGUOUS_COLUMN                      = '42702';
    case AMBIGUOUS_FUNCTION                    = '42725';
    case AMBIGUOUS_PARAMETER                   = '42P08';
    case AMBIGUOUS_ALIAS                       = '42P09';
    case INVALID_COLUMN_REFERENCE              = '42P10';
    case INVALID_COLUMN_DEFINITION             = '42611';
    case INVALID_CURSOR_DEFINITION             = '42P11';
    case INVALID_DATABASE_DEFINITION           = '42P12';
    case INVALID_FUNCTION_DEFINITION           = '42P13';
    case INVALID_PREPARED_STATEMENT_DEFINITION = '42P14';
    case INVALID_SCHEMA_DEFINITION             = '42P15';
    case INVALID_TABLE_DEFINITION              = '42P16';
    case INVALID_OBJECT_DEFINITION             = '42P17';

    /* Class 44 - WITH CHECK OPTION Violation */
    case WITH_CHECK_OPTION_VIOLATION = '44000';

    /* Class 53 - Insufficient Resources */
    case INSUFFICIENT_RESOURCES       = '53000';
    case DISK_FULL                    = '53100';
    case OUT_OF_MEMORY                = '53200';
    case TOO_MANY_CONNECTIONS         = '53300';
    case CONFIGURATION_LIMIT_EXCEEDED = '53400';

    /* Class 54 - Program Limit Exceeded */
    case PROGRAM_LIMIT_EXCEEDED = '54000';
    case STATEMENT_TOO_COMPLEX  = '54001';
    case TOO_MANY_COLUMNS       = '54011';
    case TOO_MANY_ARGUMENTS     = '54023';

    /* Class 55 - Object Not In Prerequisite State */
    case OBJECT_NOT_IN_PREREQUISITE_STATE = '55000';
    case OBJECT_IN_USE                    = '55006';
    case CANT_CHANGE_RUNTIME_PARAM        = '55P02';
    case LOCK_NOT_AVAILABLE               = '55P03';
    case UNSAFE_NEW_ENUM_VALUE_USAGE      = '55P04';

    /* Class 57 - Operator Intervention */
    case OPERATOR_INTERVENTION = '57000';
    case QUERY_CANCELED        = '57014';
    case ADMIN_SHUTDOWN        = '57P01';
    case CRASH_SHUTDOWN        = '57P02';
    case CANNOT_CONNECT_NOW    = '57P03';
    case DATABASE_DROPPED      = '57P04';
    case IDLE_SESSION_TIMEOUT  = '57P05';

    /* Class 58 - System Error (errors external to PostgreSQL itself) */
    case SYSTEM_ERROR   = '58000';
    case IO_ERROR       = '58030';
    case UNDEFINED_FILE = '58P01';
    case DUPLICATE_FILE = '58P02';

    /* Class 72 - Snapshot Failure */
    case SNAPSHOT_TOO_OLD = '72000'; // This was removed in Postgres 17

    /* Class F0 - Configuration File Error */
    case CONFIG_FILE_ERROR = 'F0000';
    case LOCK_FILE_EXISTS  = 'F0001';

    /* Class HV - Foreign Data Wrapper Error (SQL/MED) */
    case FDW_ERROR                                  = 'HV000';
    case FDW_COLUMN_NAME_NOT_FOUND                  = 'HV005';
    case FDW_DYNAMIC_PARAMETER_VALUE_NEEDED         = 'HV002';
    case FDW_FUNCTION_SEQUENCE_ERROR                = 'HV010';
    case FDW_INCONSISTENT_DESCRIPTOR_INFORMATION    = 'HV021';
    case FDW_INVALID_ATTRIBUTE_VALUE                = 'HV024';
    case FDW_INVALID_COLUMN_NAME                    = 'HV007';
    case FDW_INVALID_COLUMN_NUMBER                  = 'HV008';
    case FDW_INVALID_DATA_TYPE                      = 'HV004';
    case FDW_INVALID_DATA_TYPE_DESCRIPTORS          = 'HV006';
    case FDW_INVALID_DESCRIPTOR_FIELD_IDENTIFIER    = 'HV091';
    case FDW_INVALID_HANDLE                         = 'HV00B';
    case FDW_INVALID_OPTION_INDEX                   = 'HV00C';
    case FDW_INVALID_OPTION_NAME                    = 'HV00D';
    case FDW_INVALID_STRING_LENGTH_OR_BUFFER_LENGTH = 'HV090';
    case FDW_INVALID_STRING_FORMAT                  = 'HV00A';
    case FDW_INVALID_USE_OF_NULL_POINTER            = 'HV009';
    case FDW_TOO_MANY_HANDLES                       = 'HV014';
    case FDW_OUT_OF_MEMORY                          = 'HV001';
    case FDW_NO_SCHEMAS                             = 'HV00P';
    case FDW_OPTION_NAME_NOT_FOUND                  = 'HV00J';
    case FDW_REPLY_HANDLE                           = 'HV00K';
    case FDW_SCHEMA_NOT_FOUND                       = 'HV00Q';
    case FDW_TABLE_NOT_FOUND                        = 'HV00R';
    case FDW_UNABLE_TO_CREATE_EXECUTION             = 'HV00L';
    case FDW_UNABLE_TO_CREATE_REPLY                 = 'HV00M';
    case FDW_UNABLE_TO_ESTABLISH_CONNECTION         = 'HV00N';

    /* Class P0 - PL/pgSQL Error */
    case PLPGSQL_ERROR   = 'P0000';
    case RAISE_EXCEPTION = 'P0001';
    case NO_DATA_FOUND   = 'P0002';
    case TOO_MANY_ROWS   = 'P0003';
    case ASSERT_FAILURE  = 'P0004';

    /* Class XX - Internal Error */
    case INTERNAL_ERROR  = 'XX000';
    case DATA_CORRUPTED  = 'XX001';
    case INDEX_CORRUPTED = 'XX002';

    /**
     * Returns the case with a "canonical" name for the case with a changed name
     *
     * @return self
     */
    public function canonical(): self
    {
        return match ($this) {
            self::WARNING_STRING_DATA_RIGHT_TRUNCATION => self::STRING_DATA_RIGHT_TRUNCATION,
            self::EXTERNAL_MODIFYING_SQL_DATA_NOT_PERMITTED => self::MODIFYING_SQL_DATA_NOT_PERMITTED,
            self::EXTERNAL_PROHIBITED_SQL_STATEMENT_ATTEMPTED => self::PROHIBITED_SQL_STATEMENT_ATTEMPTED,
            self::EXTERNAL_READING_SQL_DATA_NOT_PERMITTED => self::READING_SQL_DATA_NOT_PERMITTED,
            self::EXTERNAL_NULL_VALUE_NOT_ALLOWED => self::NULL_VALUE_NOT_ALLOWED,
            default => $this
        };
    }

    /**
     * Returns the "generic subclass" case for the current one
     *
     * Generic subclass error code ends with three zeroes
     *
     * @return self
     */
    public function genericSubclass(): self
    {
        return self::from(\substr_replace($this->value, '000', 2, 3));
    }
}
