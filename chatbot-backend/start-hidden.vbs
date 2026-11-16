Set WshShell = CreateObject("WScript.Shell")
WshShell.CurrentDirectory = "C:\xampp\htdocs\SAG\chatbot-backend"
WshShell.Run "run-service.bat", 0, False

