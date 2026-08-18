package com.fandoogh.crm.fandoogh_crm

import android.app.Activity
import android.content.Intent
import android.net.Uri
import io.flutter.embedding.android.FlutterActivity
import io.flutter.embedding.engine.FlutterEngine
import io.flutter.plugin.common.MethodChannel
import java.io.File

class MainActivity : FlutterActivity() {
    private val imagePickerRequest = 4401
    private var pendingImageResult: MethodChannel.Result? = null
    private var pendingMultipleImages = false
    private var pendingImageLimit = 1

    override fun configureFlutterEngine(flutterEngine: FlutterEngine) {
        super.configureFlutterEngine(flutterEngine)
        MethodChannel(flutterEngine.dartExecutor.binaryMessenger, "com.fandoogh.crm/media")
            .setMethodCallHandler { call, result ->
                when (call.method) {
                    "pickImage" -> {
                        openImagePicker(result, multiple = false, maxCount = 1)
                    }
                    "pickImages" -> {
                        val requestedLimit = (call.argument<Int>("maxCount") ?: 5)
                            .coerceIn(1, 5)
                        openImagePicker(result, multiple = true, maxCount = requestedLimit)
                    }
                    else -> result.notImplemented()
                }
            }
    }

    private fun openImagePicker(
        result: MethodChannel.Result,
        multiple: Boolean,
        maxCount: Int,
    ) {
        if (pendingImageResult != null) {
            result.error("PICKER_BUSY", "The image picker is already open.", null)
            return
        }

        pendingImageResult = result
        pendingMultipleImages = multiple
        pendingImageLimit = maxCount
        val intent = Intent(Intent.ACTION_OPEN_DOCUMENT).apply {
            addCategory(Intent.CATEGORY_OPENABLE)
            type = "image/*"
            putExtra(Intent.EXTRA_ALLOW_MULTIPLE, multiple)
        }
        startActivityForResult(intent, imagePickerRequest)
    }

    @Deprecated("Deprecated by Android, retained for FlutterActivity compatibility.")
    override fun onActivityResult(requestCode: Int, resultCode: Int, data: Intent?) {
        super.onActivityResult(requestCode, resultCode, data)
        if (requestCode != imagePickerRequest) return
        val result = pendingImageResult ?: return
        pendingImageResult = null
        val multiple = pendingMultipleImages
        val limit = pendingImageLimit
        pendingMultipleImages = false
        pendingImageLimit = 1
        if (resultCode != Activity.RESULT_OK || data == null) {
            result.success(if (multiple) emptyList<String>() else null)
            return
        }

        val uris = mutableListOf<Uri>()
        val clipData = data.clipData
        if (clipData != null) {
            for (index in 0 until minOf(clipData.itemCount, limit)) {
                uris.add(clipData.getItemAt(index).uri)
            }
        }
        if (uris.isEmpty()) {
            data.data?.let(uris::add)
        }
        if (uris.isEmpty()) {
            result.success(if (multiple) emptyList<String>() else null)
            return
        }

        try {
            val paths = uris.map { uri -> copyImageToCache(uri) }
            if (multiple) {
                result.success(paths)
            } else {
                result.success(paths.firstOrNull())
            }
        } catch (error: Exception) {
            result.error("IMAGE_READ_FAILED", "Selected image could not be prepared.", null)
        }
    }

    private fun copyImageToCache(uri: Uri): String {
        val mimeType = contentResolver.getType(uri)
        val extension = when (mimeType) {
            "image/png" -> ".png"
            "image/webp" -> ".webp"
            else -> ".jpg"
        }
        val target = File.createTempFile("fandoogh-upload-", extension, cacheDir)
        contentResolver.openInputStream(uri).use { input ->
            requireNotNull(input) { "Selected image cannot be opened." }
            target.outputStream().use { output -> input.copyTo(output) }
        }
        return target.absolutePath
    }
}
