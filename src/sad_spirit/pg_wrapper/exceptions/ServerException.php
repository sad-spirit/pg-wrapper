<?php
/**
 * Wrapper for PHP's pgsql extension providing conversion of complex DB types
 *
 * LICENSE
 *
 * This source file is subject to BSD 2-Clause License that is bundled
 * with this package in the file LICENSE and available at the URL
 * https://raw.githubusercontent.com/sad-spirit/pg-wrapper/master/LICENSE
 *
 * @package   sad_spirit\pg_wrapper
 * @copyright 2014-2017 Alexey Borzov
 * @author    Alexey Borzov <avb@php.net>
 * @license   http://opensource.org/licenses/BSD-2-Clause BSD 2-Clause license
 * @link      https://github.com/sad-spirit/pg-wrapper
 */

declare(strict_types=1);

namespace sad_spirit\pg_wrapper\exceptions;

use sad_spirit\pg_wrapper\Exception;

/**
 * Exception thrown on failed query
 */
class ServerException extends \UnexpectedValueException implements Exception
{
    /*
     The following is the list of PostgreSQL error codes, generated from
     src/backend/utils/errcodes.txt
     */

    /* Class 00 - Successful Completion */
    public const SUCCESSFUL_COMPLETION = '00000';

    /* Class 01 - Warning */
    public const WARNING                               = '01000';
    public const DYNAMIC_RESULT_SETS_RETURNED          = '0100C';
    public const IMPLICIT_ZERO_BIT_PADDING             = '01008';
    public const NULL_VALUE_ELIMINATED_IN_SET_FUNCTION = '01003';
    public const PRIVILEGE_NOT_GRANTED                 = '01007';
    public const PRIVILEGE_NOT_REVOKED                 = '01006';
    public const STRING_DATA_RIGHT_TRUNCATION          = '01004';
    public const DEPRECATED_FEATURE                    = '01P01';

    /* Class 02 - No Data (this is also a warning class per the SQL standard) */
    public const NO_DATA                                    = '02000';
    public const NO_ADDITIONAL_DYNAMIC_RESULT_SETS_RETURNED = '02001';

    /* Class 03 - SQL Statement Not Yet Complete */
    public const SQL_STATEMENT_NOT_YET_COMPLETE = '03000';

    /* Class 08 - Connection Exception */
    public const CONNECTION_EXCEPTION                              = '08000';
    public const CONNECTION_DOES_NOT_EXIST                         = '08003';
    public const CONNECTION_FAILURE                                = '08006';
    public const SQLCLIENT_UNABLE_TO_ESTABLISH_SQLCONNECTION       = '08001';
    public const SQLSERVER_REJECTED_ESTABLISHMENT_OF_SQLCONNECTION = '08004';
    public const TRANSACTION_RESOLUTION_UNKNOWN                    = '08007';
    public const PROTOCOL_VIOLATION                                = '08P01';

    /* Class 09 - Triggered Action Exception */
    public const TRIGGERED_ACTION_EXCEPTION = '09000';

    /* Class 0A - Feature Not Supported */
    public const FEATURE_NOT_SUPPORTED = '0A000';

    /* Class 0B - Invalid Transaction Initiation */
    public const INVALID_TRANSACTION_INITIATION = '0B000';

    /* Class 0F - Locator Exception */
    public const LOCATOR_EXCEPTION             = '0F000';
    public const INVALID_LOCATOR_SPECIFICATION = '0F001';

    /* Class 0L - Invalid Grantor */
    public const INVALID_GRANTOR         = '0L000';
    public const INVALID_GRANT_OPERATION = '0LP01';

    /* Class 0P - Invalid Role Specification */
    public const INVALID_ROLE_SPECIFICATION = '0P000';

    /* Class 0Z - Diagnostics Exception */
    public const DIAGNOSTICS_EXCEPTION                               = '0Z000';
    public const STACKED_DIAGNOSTICS_ACCESSED_WITHOUT_ACTIVE_HANDLER = '0Z002';

    /* Class 20 - Case Not Found */
    public const CASE_NOT_FOUND = '20000';

    /* Class 21 - Cardinality Violation */
    public const CARDINALITY_VIOLATION = '21000';

    /* Class 22 - Data Exception */
    public const DATA_EXCEPTION                             = '22000';
    public const ARRAY_SUBSCRIPT_ERROR                      = '2202E';
    public const CHARACTER_NOT_IN_REPERTOIRE                = '22021';
    public const DATETIME_FIELD_OVERFLOW                    = '22008';
    public const DIVISION_BY_ZERO                           = '22012';
    public const ERROR_IN_ASSIGNMENT                        = '22005';
    public const ESCAPE_CHARACTER_CONFLICT                  = '2200B';
    public const INDICATOR_OVERFLOW                         = '22022';
    public const INTERVAL_FIELD_OVERFLOW                    = '22015';
    public const INVALID_ARGUMENT_FOR_LOGARITHM             = '2201E';
    public const INVALID_ARGUMENT_FOR_NTILE_FUNCTION        = '22014';
    public const INVALID_ARGUMENT_FOR_NTH_VALUE_FUNCTION    = '22016';
    public const INVALID_ARGUMENT_FOR_POWER_FUNCTION        = '2201F';
    public const INVALID_ARGUMENT_FOR_WIDTH_BUCKET_FUNCTION = '2201G';
    public const INVALID_CHARACTER_VALUE_FOR_CAST           = '22018';
    public const INVALID_DATETIME_FORMAT                    = '22007';
    public const INVALID_ESCAPE_CHARACTER                   = '22019';
    public const INVALID_ESCAPE_OCTET                       = '2200D';
    public const INVALID_ESCAPE_SEQUENCE                    = '22025';
    public const NONSTANDARD_USE_OF_ESCAPE_CHARACTER        = '22P06';
    public const INVALID_INDICATOR_PARAMETER_VALUE          = '22010';
    public const INVALID_PARAMETER_VALUE                    = '22023';
    public const INVALID_PRECEDING_OR_FOLLOWING_SIZE        = '22013';
    public const INVALID_REGULAR_EXPRESSION                 = '2201B';
    public const INVALID_ROW_COUNT_IN_LIMIT_CLAUSE          = '2201W';
    public const INVALID_ROW_COUNT_IN_RESULT_OFFSET_CLAUSE  = '2201X';
    public const INVALID_TABLESAMPLE_ARGUMENT               = '2202H';
    public const INVALID_TABLESAMPLE_REPEAT                 = '2202G';
    public const INVALID_TIME_ZONE_DISPLACEMENT_VALUE       = '22009';
    public const INVALID_USE_OF_ESCAPE_CHARACTER            = '2200C';
    public const MOST_SPECIFIC_TYPE_MISMATCH                = '2200G';
    public const NULL_VALUE_NOT_ALLOWED                     = '22004';
    public const NULL_VALUE_NO_INDICATOR_PARAMETER          = '22002';
    public const NUMERIC_VALUE_OUT_OF_RANGE                 = '22003';
    public const SEQUENCE_GENERATOR_LIMIT_EXCEEDED          = '2200H';
    public const STRING_DATA_LENGTH_MISMATCH                = '22026';
    //public const STRING_DATA_RIGHT_TRUNCATION               = '22001';
    public const SUBSTRING_ERROR                            = '22011';
    public const TRIM_ERROR                                 = '22027';
    public const UNTERMINATED_C_STRING                      = '22024';
    public const ZERO_LENGTH_CHARACTER_STRING               = '2200F';
    public const FLOATING_POINT_EXCEPTION                   = '22P01';
    public const INVALID_TEXT_REPRESENTATION                = '22P02';
    public const INVALID_BINARY_REPRESENTATION              = '22P03';
    public const BAD_COPY_FILE_FORMAT                       = '22P04';
    public const UNTRANSLATABLE_CHARACTER                   = '22P05';
    public const NOT_AN_XML_DOCUMENT                        = '2200L';
    public const INVALID_XML_DOCUMENT                       = '2200M';
    public const INVALID_XML_CONTENT                        = '2200N';
    public const INVALID_XML_COMMENT                        = '2200S';
    public const INVALID_XML_PROCESSING_INSTRUCTION         = '2200T';

    /* Class 23 - Integrity Constraint Violation */
    public const INTEGRITY_CONSTRAINT_VIOLATION = '23000';
    public const RESTRICT_VIOLATION             = '23001';
    public const NOT_NULL_VIOLATION             = '23502';
    public const FOREIGN_KEY_VIOLATION          = '23503';
    public const UNIQUE_VIOLATION               = '23505';
    public const CHECK_VIOLATION                = '23514';
    public const EXCLUSION_VIOLATION            = '23P01';

    /* Class 24 - Invalid Cursor State */
    public const INVALID_CURSOR_STATE = '24000';

    /* Class 25 - Invalid Transaction State */
    public const INVALID_TRANSACTION_STATE                            = '25000';
    public const ACTIVE_SQL_TRANSACTION                               = '25001';
    public const BRANCH_TRANSACTION_ALREADY_ACTIVE                    = '25002';
    public const HELD_CURSOR_REQUIRES_SAME_ISOLATION_LEVEL            = '25008';
    public const INAPPROPRIATE_ACCESS_MODE_FOR_BRANCH_TRANSACTION     = '25003';
    public const INAPPROPRIATE_ISOLATION_LEVEL_FOR_BRANCH_TRANSACTION = '25004';
    public const NO_ACTIVE_SQL_TRANSACTION_FOR_BRANCH_TRANSACTION     = '25005';
    public const READ_ONLY_SQL_TRANSACTION                            = '25006';
    public const SCHEMA_AND_DATA_STATEMENT_MIXING_NOT_SUPPORTED       = '25007';
    public const NO_ACTIVE_SQL_TRANSACTION                            = '25P01';
    public const IN_FAILED_SQL_TRANSACTION                            = '25P02';
    public const IDLE_IN_TRANSACTION_SESSION_TIMEOUT                  = '25P03';

    /* Class 26 - Invalid SQL Statement Name */
    public const INVALID_SQL_STATEMENT_NAME = '26000';

    /* Class 27 - Triggered Data Change Violation */
    public const TRIGGERED_DATA_CHANGE_VIOLATION = '27000';

    /* Class 28 - Invalid Authorization Specification */
    public const INVALID_AUTHORIZATION_SPECIFICATION = '28000';
    public const INVALID_PASSWORD                    = '28P01';

    /* Class 2B - Dependent Privilege Descriptors Still Exist */
    public const DEPENDENT_PRIVILEGE_DESCRIPTORS_STILL_EXIST = '2B000';
    public const DEPENDENT_OBJECTS_STILL_EXIST               = '2BP01';

    /* Class 2D - Invalid Transaction Termination */
    public const INVALID_TRANSACTION_TERMINATION = '2D000';

    /* Class 2F - SQL Routine Exception */
    public const SQL_ROUTINE_EXCEPTION                 = '2F000';
    public const FUNCTION_EXECUTED_NO_RETURN_STATEMENT = '2F005';
    public const MODIFYING_SQL_DATA_NOT_PERMITTED      = '2F002';
    public const PROHIBITED_SQL_STATEMENT_ATTEMPTED    = '2F003';
    public const READING_SQL_DATA_NOT_PERMITTED        = '2F004';

    /* Class 34 - Invalid Cursor Name */
    public const INVALID_CURSOR_NAME = '34000';

    /* Class 38 - External Routine Exception */
    public const EXTERNAL_ROUTINE_EXCEPTION         = '38000';
    public const CONTAINING_SQL_NOT_PERMITTED       = '38001';
    //public const MODIFYING_SQL_DATA_NOT_PERMITTED   = '38002';
    //public const PROHIBITED_SQL_STATEMENT_ATTEMPTED = '38003';
    //public const READING_SQL_DATA_NOT_PERMITTED     = '38004';

    /* Class 39 - External Routine Invocation Exception */
    public const EXTERNAL_ROUTINE_INVOCATION_EXCEPTION = '39000';
    public const INVALID_SQLSTATE_RETURNED             = '39001';
    //public const NULL_VALUE_NOT_ALLOWED                = '39004';
    public const TRIGGER_PROTOCOL_VIOLATED             = '39P01';
    public const SRF_PROTOCOL_VIOLATED                 = '39P02';
    public const EVENT_TRIGGER_PROTOCOL_VIOLATED       = '39P03';

    /* Class 3B - Savepoint Exception */
    public const SAVEPOINT_EXCEPTION             = '3B000';
    public const INVALID_SAVEPOINT_SPECIFICATION = '3B001';

    /* Class 3D - Invalid Catalog Name */
    public const INVALID_CATALOG_NAME = '3D000';

    /* Class 3F - Invalid Schema Name */
    public const INVALID_SCHEMA_NAME = '3F000';

    /* Class 40 - Transaction Rollback */
    public const TRANSACTION_ROLLBACK                       = '40000';
    public const TRANSACTION_INTEGRITY_CONSTRAINT_VIOLATION = '40002';
    public const SERIALIZATION_FAILURE                      = '40001';
    public const STATEMENT_COMPLETION_UNKNOWN               = '40003';
    public const DEADLOCK_DETECTED                          = '40P01';

    /* Class 42 - Syntax Error or Access Rule Violation */
    public const SYNTAX_ERROR_OR_ACCESS_RULE_VIOLATION = '42000';
    public const SYNTAX_ERROR                          = '42601';
    public const INSUFFICIENT_PRIVILEGE                = '42501';
    public const CANNOT_COERCE                         = '42846';
    public const GROUPING_ERROR                        = '42803';
    public const WINDOWING_ERROR                       = '42P20';
    public const INVALID_RECURSION                     = '42P19';
    public const INVALID_FOREIGN_KEY                   = '42830';
    public const INVALID_NAME                          = '42602';
    public const NAME_TOO_LONG                         = '42622';
    public const RESERVED_NAME                         = '42939';
    public const DATATYPE_MISMATCH                     = '42804';
    public const INDETERMINATE_DATATYPE                = '42P18';
    public const COLLATION_MISMATCH                    = '42P21';
    public const INDETERMINATE_COLLATION               = '42P22';
    public const WRONG_OBJECT_TYPE                     = '42809';
    public const GENERATED_ALWAYS                      = '428C9';
    public const UNDEFINED_COLUMN                      = '42703';
    public const UNDEFINED_FUNCTION                    = '42883';
    public const UNDEFINED_TABLE                       = '42P01';
    public const UNDEFINED_PARAMETER                   = '42P02';
    public const UNDEFINED_OBJECT                      = '42704';
    public const DUPLICATE_COLUMN                      = '42701';
    public const DUPLICATE_CURSOR                      = '42P03';
    public const DUPLICATE_DATABASE                    = '42P04';
    public const DUPLICATE_FUNCTION                    = '42723';
    public const DUPLICATE_PREPARED_STATEMENT          = '42P05';
    public const DUPLICATE_SCHEMA                      = '42P06';
    public const DUPLICATE_TABLE                       = '42P07';
    public const DUPLICATE_ALIAS                       = '42712';
    public const DUPLICATE_OBJECT                      = '42710';
    public const AMBIGUOUS_COLUMN                      = '42702';
    public const AMBIGUOUS_FUNCTION                    = '42725';
    public const AMBIGUOUS_PARAMETER                   = '42P08';
    public const AMBIGUOUS_ALIAS                       = '42P09';
    public const INVALID_COLUMN_REFERENCE              = '42P10';
    public const INVALID_COLUMN_DEFINITION             = '42611';
    public const INVALID_CURSOR_DEFINITION             = '42P11';
    public const INVALID_DATABASE_DEFINITION           = '42P12';
    public const INVALID_FUNCTION_DEFINITION           = '42P13';
    public const INVALID_PREPARED_STATEMENT_DEFINITION = '42P14';
    public const INVALID_SCHEMA_DEFINITION             = '42P15';
    public const INVALID_TABLE_DEFINITION              = '42P16';
    public const INVALID_OBJECT_DEFINITION             = '42P17';

    /* Class 44 - WITH CHECK OPTION Violation */
    public const WITH_CHECK_OPTION_VIOLATION = '44000';

    /* Class 53 - Insufficient Resources */
    public const INSUFFICIENT_RESOURCES       = '53000';
    public const DISK_FULL                    = '53100';
    public const OUT_OF_MEMORY                = '53200';
    public const TOO_MANY_CONNECTIONS         = '53300';
    public const CONFIGURATION_LIMIT_EXCEEDED = '53400';

    /* Class 54 - Program Limit Exceeded */
    public const PROGRAM_LIMIT_EXCEEDED = '54000';
    public const STATEMENT_TOO_COMPLEX  = '54001';
    public const TOO_MANY_COLUMNS       = '54011';
    public const TOO_MANY_ARGUMENTS     = '54023';

    /* Class 55 - Object Not In Prerequisite State */
    public const OBJECT_NOT_IN_PREREQUISITE_STATE = '55000';
    public const OBJECT_IN_USE                    = '55006';
    public const CANT_CHANGE_RUNTIME_PARAM        = '55P02';
    public const LOCK_NOT_AVAILABLE               = '55P03';

    /* Class 57 - Operator Intervention */
    public const OPERATOR_INTERVENTION = '57000';
    public const QUERY_CANCELED        = '57014';
    public const ADMIN_SHUTDOWN        = '57P01';
    public const CRASH_SHUTDOWN        = '57P02';
    public const CANNOT_CONNECT_NOW    = '57P03';
    public const DATABASE_DROPPED      = '57P04';

    /* Class 58 - System Error (errors external to PostgreSQL itself) */
    public const SYSTEM_ERROR   = '58000';
    public const IO_ERROR       = '58030';
    public const UNDEFINED_FILE = '58P01';
    public const DUPLICATE_FILE = '58P02';

    /* Class 72 - Snapshot Failure */
    public const SNAPSHOT_TOO_OLD = '72000';

    /* Class F0 - Configuration File Error */
    public const CONFIG_FILE_ERROR = 'F0000';
    public const LOCK_FILE_EXISTS  = 'F0001';

    /* Class HV - Foreign Data Wrapper Error (SQL/MED) */
    public const FDW_ERROR                                  = 'HV000';
    public const FDW_COLUMN_NAME_NOT_FOUND                  = 'HV005';
    public const FDW_DYNAMIC_PARAMETER_VALUE_NEEDED         = 'HV002';
    public const FDW_FUNCTION_SEQUENCE_ERROR                = 'HV010';
    public const FDW_INCONSISTENT_DESCRIPTOR_INFORMATION    = 'HV021';
    public const FDW_INVALID_ATTRIBUTE_VALUE                = 'HV024';
    public const FDW_INVALID_COLUMN_NAME                    = 'HV007';
    public const FDW_INVALID_COLUMN_NUMBER                  = 'HV008';
    public const FDW_INVALID_DATA_TYPE                      = 'HV004';
    public const FDW_INVALID_DATA_TYPE_DESCRIPTORS          = 'HV006';
    public const FDW_INVALID_DESCRIPTOR_FIELD_IDENTIFIER    = 'HV091';
    public const FDW_INVALID_HANDLE                         = 'HV00B';
    public const FDW_INVALID_OPTION_INDEX                   = 'HV00C';
    public const FDW_INVALID_OPTION_NAME                    = 'HV00D';
    public const FDW_INVALID_STRING_LENGTH_OR_BUFFER_LENGTH = 'HV090';
    public const FDW_INVALID_STRING_FORMAT                  = 'HV00A';
    public const FDW_INVALID_USE_OF_NULL_POINTER            = 'HV009';
    public const FDW_TOO_MANY_HANDLES                       = 'HV014';
    public const FDW_OUT_OF_MEMORY                          = 'HV001';
    public const FDW_NO_SCHEMAS                             = 'HV00P';
    public const FDW_OPTION_NAME_NOT_FOUND                  = 'HV00J';
    public const FDW_REPLY_HANDLE                           = 'HV00K';
    public const FDW_SCHEMA_NOT_FOUND                       = 'HV00Q';
    public const FDW_TABLE_NOT_FOUND                        = 'HV00R';
    public const FDW_UNABLE_TO_CREATE_EXECUTION             = 'HV00L';
    public const FDW_UNABLE_TO_CREATE_REPLY                 = 'HV00M';
    public const FDW_UNABLE_TO_ESTABLISH_CONNECTION         = 'HV00N';

    /* Class P0 - PL/pgSQL Error */
    public const PLPGSQL_ERROR   = 'P0000';
    public const RAISE_EXCEPTION = 'P0001';
    public const NO_DATA_FOUND   = 'P0002';
    public const TOO_MANY_ROWS   = 'P0003';
    public const ASSERT_FAILURE  = 'P0004';

    /* Class XX - Internal Error */
    public const INTERNAL_ERROR  = 'XX000';
    public const DATA_CORRUPTED  = 'XX001';
    public const INDEX_CORRUPTED = 'XX002';


    /**
     * Five-character 'SQLSTATE' error code
     * @var string
     */
    private $sqlState;

    public static function fromConnection($resource)
    {
        $message = pg_last_error($resource);
        if (PGSQL_CONNECTION_OK !== pg_connection_status($resource)) {
            return new ConnectionException($message);

        // We can only use pg_result_error_field() with async queries, so just try to parse the message
        // instead. See function pqBuildErrorMessage3() in src/interfaces/libpq/fe-protocol3.c
        } elseif (!preg_match("/^[^\\r\\n]+:  ([A-Z0-9]{5}):/", $message, $m)) {
            return new self($message);

        } else {
            switch (substr($m[1], 0, 2)) {
                case '0A':
                    return new server\FeatureNotSupportedException($message, $m[1]);

                case '22':
                    return new server\DataException($message, $m[1]);

                case '23':
                    return new server\ConstraintViolationException($message, $m[1]);
            }

            return new self($message, $m[1]);
        }
    }

    public function __construct(string $message = "", string $sqlState = "", \Throwable $previous = null)
    {
        parent::__construct($message, 0, $previous);
        $this->sqlState = $sqlState;
    }

    /**
     * Returns 'SQLSTATE' error code, if one is available
     *
     * @return string
     */
    public function getSqlState(): string
    {
        return $this->sqlState;
    }
}
