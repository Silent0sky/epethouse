# Protect WebView JavaScript interfaces
-keepattributes JavascriptInterface
-keepclassmembers class * {
    @android.webkit.JavascriptInterface <methods>;
}

# General Android rules
-keep class android.webkit.** { *; }
-dontwarn android.webkit.**
