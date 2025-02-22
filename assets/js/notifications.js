class NotificationManager {
    constructor() {
        this.container = document.createElement('div');
        this.container.className = 'notifications-container';
        document.body.appendChild(this.container);
    }

    show(message, type = 'info', duration = 5000) {
        const notification = document.createElement('div');
        const id = 'notification-' + Date.now();
        notification.className = `notification ${type}`;
        notification.id = id;
        
        const closeButton = document.createElement('span');
        closeButton.className = 'notification-close';
        closeButton.innerHTML = '×';
        closeButton.onclick = () => this.remove(id);

        notification.innerHTML = message;
        notification.appendChild(closeButton);
        
        this.container.appendChild(notification);

        if (duration > 0) {
            setTimeout(() => this.remove(id), duration);
        }
    }

    remove(id) {
        const notification = document.getElementById(id);
        if (notification) {
            notification.style.animation = 'slideOut 0.5s ease forwards';
            setTimeout(() => {
                if (notification.parentNode) {
                    notification.parentNode.removeChild(notification);
                }
            }, 500);
        }
    }

    showSuccess(message, duration = 5000) {
        this.show(message, 'success', duration);
    }

    showError(message, duration = 5000) {
        this.show(message, 'error', duration);
    }

    showWarning(message, duration = 5000) {
        this.show(message, 'warning', duration);
    }

    showInfo(message, duration = 5000) {
        this.show(message, 'info', duration);
    }
}

// Initialize the notification manager
const notificationManager = new NotificationManager();

// Function to show PHP session notifications
function showPhpNotifications(notifications) {
    if (notifications && notifications.length > 0) {
        notifications.forEach(notification => {
            notificationManager.show(notification.message, notification.type);
        });
    }
}
