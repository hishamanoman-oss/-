const form = document.getElementById("form");
const tableBody = document.getElementById("table").getElementsByTagName('tbody')[0];
const submitButton = form.querySelector('button[type="submit"]');

// متغير لتخزين ID العميل المراد تعديله
let editingClientId = null;

// دالة واحدة تعالج الإرسال (إضافة أو تعديل)
form.addEventListener("submit", function (e) {
    e.preventDefault();

    const name = document.getElementById("name").value;
    const phone = document.getElementById("phone").value;
    const email = document.getElementById("email").value;

    if (editingClientId) {
        // --- وضع التعديل (Update) ---
        fetch("update_client.php", {
            method: "POST",
            headers: { "Content-Type": "application/x-www-form-urlencoded" },
            body: `id=${editingClientId}&name=${name}&phone=${phone}&email=${email}`
        })
            .then(response => response.text())
            .then(data => {
                if (data.trim() === "success") {
                    // تحديث الصف في الجدول مباشرة
                    const row = document.querySelector(`button[data-id='${editingClientId}']`).closest("tr");
                    row.cells[0].innerText = name;
                    row.cells[1].innerText = phone;
                    row.cells[2].innerText = email;

                    resetForm(); // إعادة النموذج لوضعه الطبيعي
                } else {
                    alert("خطأ في التحديث: " + data);
                }
            });

    } else {
        // --- وضع الإضافة (Add) ---
        fetch("clients_process.php", {
            method: "POST",
            headers: { "Content-Type": "application/x-www-form-urlencoded" },
            body: `name=${name}&phone=${phone}&email=${email}`
        })
            .then(response => response.text())
            .then(data => {
                if (data.trim() === "success") {
                    // إضافة صف جديد للجدول
                    const newRow = tableBody.insertRow(0); // إضافة في الأعلى
                    newRow.innerHTML = `
                    <td>${name}</td>
                    <td>${phone}</td>
                    <td>${email}</td>
                    <td class='actions'>
                        <button class='edit' data-id='new'>edit</button> 
                        <button class='delete' data-id='new'>delete</button>
                    </td>
                `;
                    // ملاحظة: الأزرار الجديدة تحتاج تحديث الصفحة لتأخذ ID حقيقي من قاعدة البيانات
                    // أو يمكننا تعديل ملف PHP ليرجع ID العنصر المضاف. 
                    // حالياً سنكتفي بإعادة تحميل الصفحة أو تنبيه بسيط
                    location.reload();
                } else {
                    alert("خطأ في الإضافة: " + data);
                }
            });
    }
});

// معالجة أزرار الحذف والتعديل في الجدول
tableBody.addEventListener("click", function (e) {
    // 1. زر الحذف
    if (e.target.classList.contains("delete")) {
        const id = e.target.getAttribute("data-id");
        if (confirm("هل أنت متأكد من الحذف؟")) {
            fetch("delete_client.php", {
                method: "POST",
                headers: { "Content-Type": "application/x-www-form-urlencoded" },
                body: `id=${id}`
            })
                .then(response => response.text())
                .then(data => {
                    if (data.trim() === "success") {
                        e.target.closest("tr").remove();
                        // إذا كنا نعدل هذا الشخص وحذفناه، نلغي التعديل
                        if (editingClientId == id) resetForm();
                    } else {
                        alert("خطأ في الحذف: " + data);
                    }
                });
        }
    }

    // 2. زر التعديل
    if (e.target.classList.contains("edit")) {
        const row = e.target.closest("tr");
        const id = e.target.getAttribute("data-id");

        // نقل البيانات للنموذج
        document.getElementById("name").value = row.cells[0].innerText;
        document.getElementById("phone").value = row.cells[1].innerText;
        document.getElementById("email").value = row.cells[2].innerText;

        // تفعيل وضع التعديل
        editingClientId = id;
        submitButton.innerText = "Update Data"; // تغيير نص الزر
        submitButton.style.backgroundColor = "#ffa500"; // تمييز لون الزر (اختياري)
    }
});

// دالة لإعادة النموذج لوضعه الافتراضي (الإضافة)
function resetForm() {
    form.reset();
    editingClientId = null;
    submitButton.innerText = "Addition";
    submitButton.style.backgroundColor = ""; // استعادة اللون الأصلي
}
