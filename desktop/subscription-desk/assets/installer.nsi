; Unsigned per-user installer. NSIS writes its uninstaller at install time,
; so no Windows runtime is needed merely to cross-compile this private build.
!include "MUI2.nsh"
!include "x64.nsh"
!include "LogicLib.nsh"

Name "Subscription Desk"
InstallDir "$LOCALAPPDATA\Programs\Subscription Desk"
InstallDirRegKey HKCU "Software\SubscriptionDesk" "InstallDir"
RequestExecutionLevel user
SetCompressor zlib
SetCompressionLevel 7
!define MUI_ABORTWARNING
!insertmacro MUI_PAGE_WELCOME
!insertmacro MUI_PAGE_LICENSE "${PROJECT_DIR}/LICENSE"
!insertmacro MUI_PAGE_DIRECTORY
!insertmacro MUI_PAGE_INSTFILES
!insertmacro MUI_PAGE_FINISH
!insertmacro MUI_UNPAGE_CONFIRM
!insertmacro MUI_UNPAGE_INSTFILES
!insertmacro MUI_LANGUAGE "English"

Function .onInit
  ${IfNot} ${RunningX64}
    MessageBox MB_OK|MB_ICONSTOP "Subscription Desk requires 64-bit Windows."
    Abort
  ${EndIf}
  SetRegView 64
  SetShellVarContext current
FunctionEnd

Section "Subscription Desk" SecMain
  SetOutPath "$INSTDIR"
  File /r /x ".*" "${PROJECT_DIR}/dist/win-unpacked/*.*"
  WriteUninstaller "$INSTDIR\Uninstall Subscription Desk.exe"
  CreateDirectory "$SMPROGRAMS\Subscription Desk"
  CreateShortCut "$SMPROGRAMS\Subscription Desk\Subscription Desk.lnk" "$INSTDIR\Subscription Desk.exe"
  CreateShortCut "$DESKTOP\Subscription Desk.lnk" "$INSTDIR\Subscription Desk.exe"
  WriteRegStr HKCU "Software\SubscriptionDesk" "InstallDir" "$INSTDIR"
  WriteRegStr HKCU "Software\Microsoft\Windows\CurrentVersion\Uninstall\SubscriptionDesk" "DisplayName" "Subscription Desk"
  WriteRegStr HKCU "Software\Microsoft\Windows\CurrentVersion\Uninstall\SubscriptionDesk" "DisplayVersion" "${VERSION}"
  WriteRegStr HKCU "Software\Microsoft\Windows\CurrentVersion\Uninstall\SubscriptionDesk" "Publisher" "Subscription Desk (private build)"
  WriteRegStr HKCU "Software\Microsoft\Windows\CurrentVersion\Uninstall\SubscriptionDesk" "UninstallString" '"$INSTDIR\Uninstall Subscription Desk.exe"'
  WriteRegStr HKCU "Software\Microsoft\Windows\CurrentVersion\Uninstall\SubscriptionDesk" "InstallLocation" "$INSTDIR"
  WriteRegDWORD HKCU "Software\Microsoft\Windows\CurrentVersion\Uninstall\SubscriptionDesk" "NoModify" 1
  WriteRegDWORD HKCU "Software\Microsoft\Windows\CurrentVersion\Uninstall\SubscriptionDesk" "NoRepair" 1
SectionEnd

Section "Uninstall"
  SetRegView 64
  SetShellVarContext current
  ; Keep user data and unrelated files, even if a custom install directory was chosen.
  Delete "$DESKTOP\Subscription Desk.lnk"
  Delete "$SMPROGRAMS\Subscription Desk\Subscription Desk.lnk"
  RMDir "$SMPROGRAMS\Subscription Desk"
  !include "${BUILD_RESOURCES_DIR}/uninstall-files.nsh"
  DeleteRegKey HKCU "Software\SubscriptionDesk"
  DeleteRegKey HKCU "Software\Microsoft\Windows\CurrentVersion\Uninstall\SubscriptionDesk"
SectionEnd
