## Changelog

### [4.0.0] - 2023-12-07

#### Added
- Support for Laravel 10 by updating the `illuminate/support` dependency to `^10.10`.
- Enhanced logging capabilities:
  - Introduced separate logging channels for different types of logs. 
  - Now includes distinct channels for informational logs and error logs, providing better clarity and control in logging activities.
  - Configuration options in the library for specifying custom logging channels based on the log type.

#### Changed
- Updated internal mechanisms to be fully compatible with the latest Laravel 10 features and improvements.

#### Fixed
- Various minor bug fixes and performance improvements to enhance stability and efficiency.

### Notes
- This version, 4.0.0, is specifically tailored for Laravel 10, leveraging the new features and capabilities of `illuminate/support` 10.10.
- Users upgrading to this version should ensure their application is compatible with Laravel 10.
- For details on configuring the new logging channels, refer to the updated documentation section on log configuration.
- MAY NOT be compatible with Laravel 8 or earlier versions.