#!/usr/bin/env python3
"""Exercise the shipped nginx config: python3 tests/Integration/nginx-build-assets/test_nginx.py.

Requires Docker and nginx:alpine (pulled when absent). Uses an isolated disposable
container; no PHP/Vite services are started, so their routes must return 502.
"""

import http.client
import json
import pathlib
import shutil
import subprocess
import tempfile
import time
import unittest


class BuildAssetsTest(unittest.TestCase):
    @classmethod
    def setUpClass(cls):
        if not shutil.which("docker"):
            raise unittest.SkipTest("Docker CLI unavailable; nginx integration tests skipped")
        try:
            probe = subprocess.run(["docker", "info"], capture_output=True, timeout=10)
        except subprocess.TimeoutExpired:
            raise unittest.SkipTest("Docker daemon timed out; nginx integration tests skipped")
        if probe.returncode:
            raise unittest.SkipTest("Docker daemon unavailable; nginx integration tests skipped")
        cls.directory = tempfile.TemporaryDirectory(prefix="sail-nginx-assets-")
        cls.addClassCleanup(cls.directory.cleanup)
        directory = pathlib.Path(cls.directory.name)
        directory.chmod(0o755)
        public = directory / "public"
        for name, content in {
            "index.php": "<?php echo 'Laravel';",
            "build/assets/app.js": "console.log('ok');",
            "build/assets/app.mjs": "export default 1;",
            "build/assets/app.css": "body { color: red; }",
            "build/assets/logo.svg": "<svg></svg>",
            "build/assets/font.woff2": "font fixture",
            "build/app.js": "console.log('root');",
            "build/app.mjs": "export default 2;",
            "build/app.css": "body { color: blue; }",
        }.items():
            path = public / name
            path.parent.mkdir(parents=True, exist_ok=True)
            path.write_text(content)
        config = pathlib.Path(__file__).resolve().parents[3] / "runtimes/8.x/nginx.conf"
        (directory / "server.conf").write_text(config.read_text())
        (directory / "nginx.conf").write_text(
            "events {}\nhttp {\ninclude /etc/nginx/mime.types;\n"
            "log_format main '$request $status';\ninclude /fixtures/server.conf;\n}\n"
        )
        started = subprocess.run([
            "docker", "run", "-d", "-p", "127.0.0.1::80",
            "-v", f"{directory}:/fixtures:ro",
            "-v", f"{directory / 'nginx.conf'}:/etc/nginx/nginx.conf:ro",
            "-v", f"{public}:/var/www/public:ro",
            "nginx:alpine", "nginx", "-g", "daemon off;",
        ], text=True, capture_output=True)
        if started.returncode:
            raise RuntimeError(started.stderr)
        cls.container = started.stdout.strip()
        cls.addClassCleanup(subprocess.run, ["docker", "rm", "-f", cls.container], capture_output=True)
        for _ in range(100):
            inspected = subprocess.run(["docker", "inspect", cls.container], text=True, capture_output=True)
            if inspected.returncode == 0:
                ports = json.loads(inspected.stdout)[0]["NetworkSettings"]["Ports"].get("80/tcp")
                if ports:
                    cls.port = int(ports[0]["HostPort"])
                    try:
                        cls.request("/build/assets/app.js")
                        return
                    except OSError:
                        pass
            time.sleep(0.1)
        logs = subprocess.run(["docker", "logs", cls.container], text=True, capture_output=True)
        raise RuntimeError("nginx did not start: " + logs.stdout + logs.stderr)

    @classmethod
    def request(cls, path, method="GET"):
        connection = http.client.HTTPConnection("127.0.0.1", cls.port, timeout=3)
        try:
            connection.request(method, path)
            response = connection.getresponse()
            return response.status, dict(response.getheaders()), response.read()
        finally:
            connection.close()

    def test_missing_scripts_and_styles_are_empty_typed_uncacheable_404s(self):
        for prefix in ("/build/", "/build/assets/", "/build/nested/"):
            for extension, expected_type in (("js", "application/javascript"), ("mjs", "application/javascript"), ("css", "text/css"), ("JS", "application/javascript"), ("CSS", "text/css")):
                for suffix in ("", "?v=123"):
                    with self.subTest(prefix=prefix, extension=extension, suffix=suffix):
                        status, headers, body = self.request(prefix + "missing." + extension + suffix)
                        self.assertEqual(404, status)
                        self.assertEqual(b"", body)
                        self.assertEqual(expected_type, headers.get("Content-Type"))
                        self.assertEqual("0", headers.get("Content-Length"))
                        self.assertEqual("no-store", headers.get("Cache-Control"))
                        self.assertNotIn("Expires", headers)

    def test_missing_asset_head_is_typed_and_uncacheable(self):
        for extension, expected_type in (("js", "application/javascript"), ("mjs", "application/javascript"), ("css", "text/css")):
            with self.subTest(extension=extension):
                status, headers, body = self.request("/build/assets/missing." + extension, "HEAD")
                self.assertEqual(404, status)
                self.assertEqual(b"", body)
                self.assertEqual(expected_type, headers.get("Content-Type"))
                self.assertEqual("no-store", headers.get("Cache-Control"))

    def test_existing_scripts_and_styles_remain_immutable(self):
        for prefix in ("/build/", "/build/assets/"):
            for extension in ("js", "mjs", "css"):
                with self.subTest(prefix=prefix, extension=extension):
                    status, headers, body = self.request(prefix + "app." + extension)
                    self.assertEqual(200, status)
                    self.assertTrue(body)
                    self.assertIn("immutable", headers["Cache-Control"])
                    self.assertIn("Expires", headers)
                    self.assertNotIn("no-store", headers["Cache-Control"])

    def test_images_and_fonts_keep_existing_behavior(self):
        for asset in ("logo.svg", "font.woff2"):
            status, headers, body = self.request("/build/assets/" + asset)
            self.assertEqual(200, status)
            self.assertIn("immutable", headers["Cache-Control"])
        status, headers, body = self.request("/build/assets/missing.svg")
        self.assertEqual(404, status)
        self.assertIn(b"<html>", body)

    def test_pages_and_scripts_outside_build_still_reach_php(self):
        for path in ("/dashboard", "/other/missing.js", "/build/page", "/build/missing.js/page"):
            with self.subTest(path=path):
                self.assertEqual(502, self.request(path)[0])

    def test_vite_still_uses_proxy(self):
        self.assertEqual(502, self.request("/vite/build/missing.js")[0])


if __name__ == "__main__":
    unittest.main(verbosity=2)
